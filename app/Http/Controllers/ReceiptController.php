<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\PosPolicy;
use App\Policies\RefundPolicy;
use App\Support\ReceiptBusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReceiptController extends Controller
{
    public function show(Request $request, int $order): JsonResponse|View
    {
        [$actor, $tenant] = $this->context($request);
        $order = $this->visibleOrder($actor, $tenant, $order);

        return $this->render($request, $actor, $tenant, $order);
    }

    public function reprint(Request $request, int $order): JsonResponse|View
    {
        [$actor, $tenant] = $this->context($request);
        $order = DB::transaction(function () use ($actor, $tenant, $order, $request): Order {
            $locked = Order::query()
                ->whereKey($order)
                ->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $branch = Branch::query()
                ->whereKey($locked->branch_id)
                ->where('tenant_id', $tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(app(PosPolicy::class)->viewBranch($actor, $branch), 404);
            $this->assertReceipt($locked);
            DB::table('audit_logs')->insert([
                'tenant_id' => $tenant->getKey(),
                'branch_id' => $branch->getKey(),
                'actor_user_id' => $actor->getKey(),
                'actor_type' => 'user',
                'action' => 'receipt.reprinted',
                'subject_type' => 'order',
                'subject_id' => (string) $locked->getKey(),
                'outcome' => 'success',
                'reason_code' => 'receipt_reprint',
                'before_json' => null,
                'after_json' => json_encode([
                    'receipt_number' => $locked->receipt_number,
                    'verification_reference' => data_get($locked->receipt_snapshot_json, 'verification_reference'),
                ], JSON_THROW_ON_ERROR),
                'request_id' => $this->requestId($request),
                'occurred_at' => now('UTC'),
            ]);

            return $locked;
        });

        $response = $this->render($request, $actor, $tenant, $order);

        return $response instanceof JsonResponse ? $response->header('Receipt-Reprinted', 'true') : $response;
    }

    /** @return array{User, Tenant} */
    private function context(Request $request): array
    {
        $actor = User::query()
            ->whereKey($request->user()->getAuthIdentifier())
            ->where('status', 'active')
            ->firstOrFail();
        $tenant = Tenant::query()
            ->whereKey($actor->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        return [$actor, $tenant];
    }

    private function visibleOrder(User $actor, Tenant $tenant, int $orderId): Order
    {
        $order = Order::query()
            ->whereKey($orderId)
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();
        $branch = Branch::query()
            ->whereKey($order->branch_id)
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->firstOrFail();
        abort_unless(app(PosPolicy::class)->viewBranch($actor, $branch), 404);
        $this->assertReceipt($order);

        return $order;
    }

    private function assertReceipt(Order $order): void
    {
        if ($order->status === 'draft' || $order->receipt_number === null || ! is_array($order->receipt_snapshot_json)) {
            throw new HttpException(409, 'This order has no issued receipt.');
        }
    }

    private function render(Request $request, User $actor, Tenant $tenant, Order $order): JsonResponse|View
    {
        $receipt = $order->receipt_snapshot_json;
        $branch = Branch::query()
            ->whereKey($order->branch_id)
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->firstOrFail();
        $payment = Payment::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('order_id', $order->getKey())
            ->first();
        $refund = Refund::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('order_id', $order->getKey())
            ->first();
        $eligibility = $this->refundEligibility($tenant, $order, $branch, $payment);
        $refundPolicy = app(RefundPolicy::class);
        $canRequest = $refund === null && $eligibility['eligible'] && $refundPolicy->request($actor, $tenant->getKey(), $branch->getKey());
        $canApprove = $refund !== null && $refund->status === 'requested' && $refundPolicy->approve($actor, $refund);
        $canExecute = $refund !== null && $refund->status === 'approved'
            && (int) $refund->approved_by_user_id !== (int) $actor->getKey()
            && $refundPolicy->execute($actor, $refund);

        $payload = [
            'receipt' => $receipt,
            'order' => $order,
            'branch' => $branch,
            'payment' => $payment,
            'refund' => $refund,
            'eligibility' => $eligibility,
            'canRequest' => $canRequest,
            'canApprove' => $canApprove,
            'canExecute' => $canExecute,
            'requestKey' => (string) Str::uuid(),
            'executionKey' => (string) Str::uuid(),
            'receipt_number' => $order->receipt_number,
            'status' => $order->status,
            'order_status' => $order->status,
            'receipt_status' => data_get($receipt, 'status'),
            'refund_status' => $refund?->status,
            'refunded_minor' => (int) $order->refunded_minor,
            'verification_reference' => data_get($receipt, 'verification_reference'),
            'qr_payload' => data_get($receipt, 'qr_payload'),
        ];
        if (! $request->expectsJson()) {
            return view('receipts.show', $payload);
        }

        return response()->json([
            'receipt' => $receipt,
            'receipt_number' => $order->receipt_number,
            'status' => $order->status,
            'order_status' => $order->status,
            'receipt_status' => data_get($receipt, 'status'),
            'refund_status' => $refund?->status,
            'refunded_minor' => (int) $order->refunded_minor,
            'verification_reference' => data_get($receipt, 'verification_reference'),
            'qr_payload' => data_get($receipt, 'qr_payload'),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', Str::uuid());
    }

    /** @return array{eligible: bool, reason: string|null} */
    private function refundEligibility(Tenant $tenant, Order $order, Branch $branch, ?Payment $payment): array
    {
        if ($order->status !== 'paid') {
            return ['eligible' => false, 'reason' => __('receipts.ineligible_not_paid')];
        }
        if ($payment === null || $payment->status !== 'posted' || $payment->method !== 'cash' || $payment->currency !== 'EGP'
            || (int) $order->refunded_minor !== 0 || (int) $order->total_minor !== (int) $payment->amount_minor
            || (int) $order->paid_minor !== (int) $payment->amount_minor || $order->currency !== 'EGP'
            || $order->currency !== $branch->currency || $order->receipt_number === null || ! is_array($order->receipt_snapshot_json)) {
            return ['eligible' => false, 'reason' => __('receipts.ineligible_policy')];
        }

        $receiptTimezone = ReceiptBusinessDate::timezone($order);
        if ($receiptTimezone === null) {
            return ['eligible' => false, 'reason' => __('receipts.ineligible_policy')];
        }
        $postedDate = CarbonImmutable::parse($payment->posted_at, 'UTC')->setTimezone($receiptTimezone)->toDateString();
        if ($postedDate !== CarbonImmutable::now($receiptTimezone)->toDateString()) {
            return ['eligible' => false, 'reason' => __('receipts.ineligible_date')];
        }

        $seen = [];
        foreach (OrderItem::query()->where('tenant_id', $tenant->getKey())->where('order_id', $order->getKey())->where('item_kind', 'ticket')->get() as $item) {
            $metadata = is_array($item->metadata_json) ? $item->metadata_json : [];
            $ticketIds = $metadata['ticket_ids'] ?? null;
            if (! is_array($ticketIds) || count($ticketIds) !== (int) $item->quantity) {
                return ['eligible' => false, 'reason' => __('receipts.ineligible_ticket')];
            }
            foreach ($ticketIds as $ticketId) {
                if (! is_numeric($ticketId) || (int) $ticketId < 1 || isset($seen[(int) $ticketId])) {
                    return ['eligible' => false, 'reason' => __('receipts.ineligible_ticket')];
                }
                $seen[(int) $ticketId] = true;
                $ticket = Ticket::query()->whereKey((int) $ticketId)->where('tenant_id', $tenant->getKey())->where('branch_id', $branch->getKey())->first();
                if (! $ticket || (int) $item->ticket_type_id !== (int) $ticket->ticket_type_id || $ticket->status !== 'issued'
                    || $ticket->assignment_locked_at !== null || $ticket->consumed_at !== null || (int) $ticket->uses_count !== 0
                    || $ticket->scans()->where('result', 'accepted')->exists() || $ticket->playSession()->where('tenant_id', $tenant->getKey())->exists()) {
                    return ['eligible' => false, 'reason' => __('receipts.ineligible_ticket')];
                }
            }
        }

        return ['eligible' => true, 'reason' => null];
    }
}
