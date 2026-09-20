<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\PosPolicy;
use App\Policies\RefundPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class TransactionHistoryController extends Controller
{
    private const STATUSES = ['paid', 'refunded'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->context($request);
        $policy = app(PosPolicy::class);
        abort_unless($policy->viewAny($actor), 403);

        $branches = $actor->accessibleBranches()
            ->where('branches.tenant_id', $tenant->getKey())
            ->orderBy('branches.name')
            ->get()
            ->filter(fn (Branch $branch): bool => $policy->viewBranch($actor, $branch))
            ->values();
        $filters = Validator::make($request->query(), [
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
            'local_date' => ['nullable', 'date_format:Y-m-d'],
            'cashier_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $selectedBranch = $this->selectedBranch($request, $branches, $filters['branch_id'] ?? null);
        $query = Order::query()
            ->with([
                'branch:id,name,timezone,currency',
                'paidBy:id,name',
                'payments' => fn ($query) => $query->where('tenant_id', $tenant->getKey())->select([
                    'id', 'tenant_id', 'branch_id', 'order_id', 'method', 'status', 'amount_minor', 'currency', 'posted_by_user_id', 'posted_at',
                ]),
                'refund.requestedBy:id,name',
                'refund.approvedBy:id,name',
                'refund.executedBy:id,name',
            ])
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('branch_id', $selectedBranch ? [$selectedBranch->getKey()] : [-1])
            ->whereIn('status', self::STATUSES)
            ->whereNotNull('receipt_number')
            ->whereHas('payments', fn (Builder $payment): Builder => $payment->where('tenant_id', $tenant->getKey())->where('status', 'posted'))
            ->when(filled($filters['receipt_number'] ?? null), fn (Builder $query) => $query->where('receipt_number', 'like', '%'.trim($filters['receipt_number']).'%'))
            ->when(isset($filters['status']) && $filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['cashier_id']) && $filters['cashier_id'] !== null, fn (Builder $query) => $query->where('paid_by_user_id', $filters['cashier_id']));

        if ($selectedBranch && filled($filters['local_date'] ?? null)) {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $filters['local_date'], $selectedBranch->timezone)->startOfDay();
            $to = $from->endOfDay();
            $query->whereBetween('paid_at', [$from->utc(), $to->utc()]);
        }

        $transactions = $query->orderByDesc('paid_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $cashiers = $this->cashiers($tenant, $selectedBranch);
        $refundPolicy = app(RefundPolicy::class);
        $presentations = $transactions->getCollection()->mapWithKeys(function (Order $order) use ($actor, $tenant, $refundPolicy): array {
            $branch = $order->branch;
            $payment = $order->payments->first();
            $refund = $order->refund;
            $eligibility = $branch && $payment ? $this->refundEligibility($tenant, $order, $branch, $payment) : [
                'eligible' => false,
                'reason' => __('transactions.eligibility_missing_payment'),
            ];

            return [$order->getKey() => [
                'eligibility' => $eligibility,
                'can_request' => $refund === null && $eligibility['eligible'] && $branch !== null && $refundPolicy->request($actor, $tenant->getKey(), $branch->getKey()),
                'can_approve' => $refund !== null && $refund->status === 'requested' && $refundPolicy->approve($actor, $refund),
                'can_execute' => $refund !== null && $refund->status === 'approved' && (int) $refund->approved_by_user_id !== (int) $actor->getKey() && $refundPolicy->execute($actor, $refund),
                'request_key' => (string) str()->uuid(),
                'execution_key' => (string) str()->uuid(),
            ]];
        });

        return view('transactions.index', compact('actor', 'tenant', 'branches', 'selectedBranch', 'cashiers', 'filters', 'transactions', 'presentations'));
    }

    /** @return array{User, Tenant} */
    private function context(Request $request): array
    {
        $actor = User::query()->whereKey($request->user()->getAuthIdentifier())->where('status', 'active')->firstOrFail();
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();

        return [$actor, $tenant];
    }

    private function selectedBranch(Request $request, Collection $branches, mixed $requestedId): ?Branch
    {
        $id = $requestedId ?? $request->session()->get('branch_id');
        if ($id !== null) {
            $selected = $branches->firstWhere('id', (int) $id);
            abort_unless($selected, 404);

            return $selected;
        }

        return $branches->first();
    }

    private function cashiers(Tenant $tenant, ?Branch $branch): Collection
    {
        if ($branch === null) {
            return collect();
        }

        $ids = Order::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->whereIn('status', self::STATUSES)
            ->whereNotNull('paid_by_user_id')
            ->distinct()
            ->pluck('paid_by_user_id');

        return User::query()->where('tenant_id', $tenant->getKey())->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    /** @return array{eligible: bool, reason: string|null} */
    private function refundEligibility(Tenant $tenant, Order $order, Branch $branch, Payment $payment): array
    {
        if ($order->status !== 'paid') {
            return ['eligible' => false, 'reason' => __('transactions.eligibility_not_paid')];
        }
        if ($payment->status !== 'posted' || $payment->method !== 'cash' || $payment->currency !== 'EGP'
            || (int) $order->refunded_minor !== 0 || (int) $order->lock_version < 1
            || (int) $order->total_minor !== (int) $payment->amount_minor || (int) $order->paid_minor !== (int) $payment->amount_minor
            || $order->currency !== 'EGP' || $order->currency !== $branch->currency
            || $order->receipt_number === null || ! is_array($order->receipt_snapshot_json)) {
            return ['eligible' => false, 'reason' => __('transactions.eligibility_policy')];
        }

        $postedDate = CarbonImmutable::parse($payment->posted_at, 'UTC')->setTimezone($branch->timezone)->toDateString();
        if ($postedDate !== CarbonImmutable::now($branch->timezone)->toDateString()) {
            return ['eligible' => false, 'reason' => __('transactions.eligibility_date')];
        }

        $seen = [];
        foreach (OrderItem::query()->where('tenant_id', $tenant->getKey())->where('order_id', $order->getKey())->where('item_kind', 'ticket')->get() as $item) {
            $metadata = is_array($item->metadata_json) ? $item->metadata_json : [];
            $ticketIds = $metadata['ticket_ids'] ?? null;
            if (! is_array($ticketIds) || count($ticketIds) !== (int) $item->quantity) {
                return ['eligible' => false, 'reason' => __('transactions.eligibility_ticket')];
            }
            foreach ($ticketIds as $ticketId) {
                if (! is_numeric($ticketId) || (int) $ticketId < 1 || isset($seen[(int) $ticketId])) {
                    return ['eligible' => false, 'reason' => __('transactions.eligibility_ticket')];
                }
                $seen[(int) $ticketId] = true;
                $ticket = Ticket::query()->whereKey((int) $ticketId)->where('tenant_id', $tenant->getKey())->where('branch_id', $branch->getKey())->first();
                if (! $ticket || (int) $item->ticket_type_id !== (int) $ticket->ticket_type_id || $ticket->status !== 'issued'
                    || $ticket->assignment_locked_at !== null || $ticket->consumed_at !== null || (int) $ticket->uses_count !== 0
                    || $ticket->scans()->where('result', 'accepted')->exists() || $ticket->playSession()->where('tenant_id', $tenant->getKey())->exists()) {
                    return ['eligible' => false, 'reason' => __('transactions.eligibility_ticket')];
                }
            }
        }

        return ['eligible' => true, 'reason' => null];
    }
}
