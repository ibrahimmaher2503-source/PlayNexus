<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\RefundPolicy;
use App\Support\ReceiptBusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProcessCashRefund
{
    public function request(User $actor, Tenant $tenant, Order $order, int $expectedVersion, string $reason, string $key, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $tenant, $order, $expectedVersion, $reason, $key, $requestId): array {
            [$actor, $tenant, $order, $payment, $branch] = $this->context($actor, $tenant, $order->getKey());
            abort_unless(app(RefundPolicy::class)->request($actor, $tenant->getKey(), $branch->getKey()), 403);
            $fingerprint = $this->fingerprint($order, $payment, $expectedVersion, $reason);
            $existing = Refund::query()->where('tenant_id', $tenant->getKey())->where('request_idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint) || (int) $existing->order_id !== (int) $order->getKey()) {
                    throw new HttpException(409, 'The refund idempotency key was reused with different data.');
                }

                return $this->result($existing, false);
            }
            if (Refund::query()->where('tenant_id', $tenant->getKey())->where('order_id', $order->getKey())->exists()) {
                throw new HttpException(409, 'A refund workflow already exists for this order.');
            }
            $this->assertEligible($order, $payment, $branch, $expectedVersion);
            $this->assertTicketEligibility($tenant, $branch, $order);
            $now = CarbonImmutable::now('UTC');
            $refund = Refund::query()->create([
                'tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'order_id' => $order->getKey(), 'payment_id' => $payment->getKey(),
                'requested_by_user_id' => $actor->getKey(), 'amount_minor' => $payment->amount_minor, 'currency' => $payment->currency,
                'reason' => $reason, 'status' => 'requested', 'expected_order_lock_version' => $expectedVersion,
                'request_idempotency_key' => $key, 'request_fingerprint' => $fingerprint, 'requested_at' => $now,
            ]);
            $this->audit($tenant, $branch, $actor, $order, 'order.refund_requested', $requestId, $refund);

            return $this->result($refund, true);
        });
    }

    public function approve(User $actor, Tenant $tenant, Refund $refund, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $tenant, $refund, $requestId): array {
            [$actor, $tenant] = $this->lockIdentity($actor, $tenant);
            $refund = Refund::query()->whereKey($refund->getKey())->where('tenant_id', $tenant->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(app(RefundPolicy::class)->approve($actor, $refund), 403);
            [$order, $payment, $branch] = $this->lockedCommercialContext($tenant, $refund->order_id);
            if ($refund->status !== 'requested') {
                throw new HttpException(409, 'The refund was already reviewed.');
            }
            $this->assertEligible($order, $payment, $branch, $refund->expected_order_lock_version);
            $this->assertTicketEligibility($tenant, $branch, $order);
            $refund->forceFill(['status' => 'approved', 'approved_by_user_id' => $actor->getKey(), 'approved_at' => CarbonImmutable::now('UTC')])->save();
            $this->audit($tenant, $branch, $actor, $order, 'order.refund_approved', $requestId, $refund);

            return $this->result($refund, true);
        });
    }

    public function execute(User $actor, Tenant $tenant, Refund $refund, int $expectedVersion, string $key, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $tenant, $refund, $expectedVersion, $key, $requestId): array {
            [$actor, $tenant] = $this->lockIdentity($actor, $tenant);
            $refund = Refund::query()->whereKey($refund->getKey())->where('tenant_id', $tenant->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(app(RefundPolicy::class)->execute($actor, $refund), 403);
            [$order, $payment, $branch] = $this->lockedCommercialContext($tenant, $refund->order_id);
            $fingerprint = hash('sha256', $refund->getKey().'|'.$order->getKey().'|'.$expectedVersion.'|'.$refund->amount_minor.'|'.$refund->currency);
            $keyOwner = Refund::query()->where('tenant_id', $tenant->getKey())->where('execution_idempotency_key', $key)->lockForUpdate()->first();
            if ($keyOwner && (int) $keyOwner->getKey() !== (int) $refund->getKey()) {
                throw new HttpException(409, 'The execution idempotency key belongs to another refund.');
            }
            if ($refund->status === 'refunded') {
                if ($refund->execution_idempotency_key === $key && hash_equals((string) $refund->execution_fingerprint, $fingerprint)) {
                    return $this->result($refund, false);
                }
                throw new HttpException(409, 'The refund was already executed.');
            }
            if ($refund->status !== 'approved' || (int) $refund->approved_by_user_id === (int) $actor->getKey()) {
                throw new HttpException(409, 'A separate manager approval is required.');
            }
            $this->assertEligible($order, $payment, $branch, $expectedVersion);
            $tickets = $this->assertTicketEligibility($tenant, $branch, $order);
            if ((int) $refund->amount_minor !== (int) $payment->amount_minor || $refund->currency !== $payment->currency) {
                throw new HttpException(409, 'The approved refund no longer matches the payment.');
            }
            $now = CarbonImmutable::now('UTC');
            $order->forceFill(['status' => 'refunded', 'refunded_minor' => $payment->amount_minor, 'lock_version' => (int) $order->lock_version + 1])->save();
            foreach ($tickets as $ticket) {
                $ticket->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                    'cancelled_by_user_id' => $actor->getKey(),
                    'cancellation_reason' => 'financial_refund',
                    'lock_version' => (int) $ticket->lock_version + 1,
                ])->save();
            }
            $refund->forceFill([
                'status' => 'refunded', 'executed_by_user_id' => $actor->getKey(), 'executed_at' => $now,
                'execution_idempotency_key' => $key, 'execution_fingerprint' => $fingerprint,
            ])->save();
            $this->audit($tenant, $branch, $actor, $order, 'order.refund_executed', $requestId, $refund);

            return $this->result($refund, true);
        });
    }

    private function context(User $actor, Tenant $tenant, int $orderId): array
    {
        [$actor, $tenant] = $this->lockIdentity($actor, $tenant);
        [$order, $payment, $branch] = $this->lockedCommercialContext($tenant, $orderId);

        return [$actor, $tenant, $order, $payment, $branch];
    }

    private function lockIdentity(User $actor, Tenant $tenant): array
    {
        $tenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
        $actor = User::query()->whereKey($actor->getKey())->where('tenant_id', $tenant->getKey())->where('status', 'active')->lockForUpdate()->firstOrFail();

        return [$actor, $tenant];
    }

    private function lockedCommercialContext(Tenant $tenant, int $orderId): array
    {
        $order = Order::query()->whereKey($orderId)->where('tenant_id', $tenant->getKey())->lockForUpdate()->firstOrFail();
        $branch = Branch::query()->whereKey($order->branch_id)->where('tenant_id', $tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
        $payment = Payment::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('order_id', $order->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return [$order, $payment, $branch];
    }

    private function assertEligible(Order $order, Payment $payment, Branch $branch, int $expectedVersion): void
    {
        if ($order->status !== 'paid' || $payment->status !== 'posted' || $payment->method !== 'cash'
            || (int) $order->lock_version !== $expectedVersion || (int) $order->refunded_minor !== 0
            || (int) $payment->amount_minor < 1 || (int) $order->total_minor !== (int) $payment->amount_minor
            || (int) $payment->order_id !== (int) $order->getKey() || (int) $payment->branch_id !== (int) $branch->getKey()
            || (int) $order->paid_minor !== (int) $payment->amount_minor || $order->currency !== $payment->currency
            || $order->currency !== $branch->currency || $order->currency !== 'EGP'
            || $order->receipt_number === null || ! is_array($order->receipt_snapshot_json)) {
            throw new HttpException(409, 'The cash payment is not eligible for a full refund.');
        }
        $receiptTimezone = ReceiptBusinessDate::timezone($order);
        if ($receiptTimezone === null) {
            throw new HttpException(409, 'The receipt business-date record is invalid.');
        }
        $paidDate = CarbonImmutable::parse($payment->posted_at)->setTimezone($receiptTimezone)->toDateString();
        if ($paidDate !== CarbonImmutable::now($receiptTimezone)->toDateString()) {
            throw new HttpException(409, 'Cash refunds are limited to the original branch business date.');
        }
    }

    /** @return list<Ticket> */
    private function assertTicketEligibility(Tenant $tenant, Branch $branch, Order $order): array
    {
        $tickets = [];
        $seen = [];
        foreach ($order->items()->where('item_kind', 'ticket')->get() as $item) {
            $metadata = is_array($item->metadata_json) ? $item->metadata_json : [];
            $ticketIds = $metadata['ticket_ids'] ?? null;
            if (! is_array($ticketIds) || count($ticketIds) !== (int) $item->quantity) {
                throw new HttpException(409, 'A ticket linked to this payment is no longer refundable.');
            }
            foreach ($ticketIds as $ticketId) {
                if (! is_numeric($ticketId) || (int) $ticketId < 1 || isset($seen[(int) $ticketId])) {
                    throw new HttpException(409, 'A ticket linked to this payment is no longer refundable.');
                }
                $seen[(int) $ticketId] = true;
                $ticket = Ticket::query()
                    ->whereKey((int) $ticketId)
                    ->where('tenant_id', $tenant->getKey())
                    ->where('branch_id', $branch->getKey())
                    ->lockForUpdate()
                    ->first();
                if (! $ticket
                    || (int) $item->ticket_type_id !== (int) $ticket->ticket_type_id
                    || $ticket->status !== 'issued'
                    || $ticket->assignment_locked_at !== null
                    || $ticket->consumed_at !== null
                    || (int) $ticket->uses_count !== 0
                    || $ticket->scans()->where('result', 'accepted')->exists()
                    || $ticket->playSession()->where('tenant_id', $tenant->getKey())->exists()) {
                    throw new HttpException(409, 'A ticket linked to this payment is no longer refundable.');
                }
                $tickets[] = $ticket;
            }
        }

        return $tickets;
    }

    private function fingerprint(Order $order, Payment $payment, int $version, string $reason): string
    {
        return hash('sha256', $order->getKey().'|'.$payment->getKey().'|'.$version.'|'.$payment->amount_minor.'|'.$payment->currency.'|'.$reason);
    }

    private function result(Refund $refund, bool $created): array
    {
        $order = Order::query()
            ->where('tenant_id', $refund->tenant_id)
            ->whereKey($refund->order_id)
            ->first();
        $receipt = $order?->receipt_snapshot_json;

        return [
            'created' => $created,
            'refund_id' => $refund->getKey(),
            'order_id' => $refund->order_id,
            'status' => $refund->status,
            'amount_minor' => $refund->amount_minor,
            'currency' => $refund->currency,
            'reason' => $refund->reason,
            'executed_at' => $refund->executed_at?->toIso8601String(),
            'receipt' => $receipt,
            'receipt_number' => $order?->receipt_number,
            'receipt_status' => data_get($receipt, 'status'),
            'order_status' => $order?->status,
            'refund_status' => $refund->status,
            'refunded_minor' => (int) ($order?->refunded_minor ?? 0),
            'verification_reference' => data_get($receipt, 'verification_reference'),
            'qr_payload' => data_get($receipt, 'qr_payload'),
        ];
    }

    private function audit(Tenant $tenant, Branch $branch, User $actor, Order $order, string $action, string $requestId, Refund $refund): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'actor_user_id' => $actor->getKey(), 'actor_type' => 'user',
            'action' => $action, 'subject_type' => 'order', 'subject_id' => (string) $order->getKey(), 'outcome' => 'success', 'reason_code' => 'cash_refund',
            'before_json' => null, 'after_json' => json_encode(['refund_id' => $refund->getKey(), 'status' => $refund->status, 'amount_minor' => $refund->amount_minor, 'reason' => $refund->reason], JSON_THROW_ON_ERROR),
            'request_id' => $requestId, 'occurred_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
