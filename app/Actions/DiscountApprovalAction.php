<?php

namespace App\Actions;

use App\Models\ApprovalRecord;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ApprovalRecordPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DiscountApprovalAction
{
    public function request(User $actor, Tenant $tenant, Order $order, int $expectedVersion, int $discountMinor, array $payload, string $reason, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $tenant, $order, $expectedVersion, $discountMinor, $payload, $reason, $requestId): array {
            [$tenant, $actor] = $this->lockContext($tenant, $actor);
            $order = Order::query()->whereKey($order->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $order->load('items');
            $branch = $this->branch($tenant, $order);
            abort_unless(app(ApprovalRecordPolicy::class)->request($actor, $order), 403);
            $this->assertDraft($order, $expectedVersion, $discountMinor);
            $this->assertReconciled($order);
            $this->assertPayloadMatchesOrder($order, $payload);
            $now = CarbonImmutable::now('UTC');
            $approval = ApprovalRecord::query()->create([
                'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'order_id' => $order->id,
                'requested_by_user_id' => $actor->id, 'discount_minor' => $discountMinor,
                'currency' => strtoupper((string) $order->currency), 'reason' => $reason,
                'request_payload_json' => $this->orderBinding($order),
                'payload_fingerprint' => $this->fingerprint($tenant, $branch, $order, $expectedVersion, $discountMinor, $payload),
                'expected_order_lock_version' => $expectedVersion, 'status' => 'requested',
                'expires_at' => $now->addMinutes(10), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->audit($tenant, $branch, $actor, $order, 'order.discount_requested', $requestId, [
                'approval_id' => $approval->id, 'discount_minor' => $discountMinor,
                'expected_order_lock_version' => $expectedVersion, 'reason' => $reason,
            ]);

            return ['created' => true, 'approval_id' => $approval->id, 'status' => $approval->status,
                'order_id' => $order->id, 'discount_minor' => $discountMinor, 'currency' => $approval->currency,
                'expected_order_lock_version' => $expectedVersion, 'expires_at' => $approval->expires_at?->toIso8601String()];
        });
    }

    public function approve(User $actor, Tenant $tenant, ApprovalRecord $approval, string $requestId): array
    {
        return $this->review($actor, $tenant, $approval, true, null, $requestId);
    }

    public function reject(User $actor, Tenant $tenant, ApprovalRecord $approval, string $reason, string $requestId): array
    {
        return $this->review($actor, $tenant, $approval, false, $reason, $requestId);
    }

    /**
     * Consume an approval while the caller's surrounding transaction is open.
     * The linked order and approval are locked again here so payment cannot race
     * a cart change or consume an approval for a different order.
     */
    public function consumeLocked(User $actor, Tenant $tenant, ApprovalRecord $approval, int $expectedVersion, int $discountMinor, array $payload, string $requestId): array
    {
        $approval = ApprovalRecord::query()->whereKey($approval->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
        abort_unless(app(ApprovalRecordPolicy::class)->consume($actor, $approval), 403);
        $order = Order::query()->with('items')->whereKey($approval->order_id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
        $branch = $this->branch($tenant, $order);
        if ((int) $approval->branch_id !== (int) $branch->id) {
            throw new HttpException(409, 'The approval branch does not match the order.');
        }
        $this->assertDraft($order, $expectedVersion, $discountMinor);
        $this->assertPayloadMatchesOrder($order, $payload);
        if ($approval->status !== 'approved') {
            throw new HttpException(409, 'The discount approval is not consumable.');
        }
        if ($approval->expires_at === null || $approval->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            throw new HttpException(409, 'The discount approval has expired.');
        }
        if ((int) $approval->expected_order_lock_version !== $expectedVersion
            || ! hash_equals((string) $approval->payload_fingerprint, $this->fingerprint($tenant, $branch, $order, $expectedVersion, $discountMinor, $payload))) {
            throw new HttpException(409, 'The discount approval does not match the current cart.');
        }
        if ((int) $approval->discount_minor !== $discountMinor || strtoupper((string) $approval->currency) !== strtoupper((string) $order->currency)) {
            throw new HttpException(409, 'The approved discount does not match the order.');
        }
        $this->assertReconciled($order);
        $now = CarbonImmutable::now('UTC');
        $beforeVersion = (int) $order->lock_version;
        $remainingDiscount = $discountMinor;
        foreach ($order->items->sortBy('line_number') as $item) {
            if ($remainingDiscount === 0) {
                break;
            }
            $lineTotal = (int) $item->line_total_minor;
            $allocated = min($remainingDiscount, $lineTotal);
            $item->forceFill([
                'discount_minor' => (int) $item->discount_minor + $allocated,
                'line_total_minor' => $lineTotal - $allocated,
            ])->save();
            $remainingDiscount -= $allocated;
        }
        if ($remainingDiscount > 0) {
            throw new HttpException(409, 'The discount cannot be allocated to the persisted cart lines.');
        }
        $order->forceFill([
            'discount_minor' => $discountMinor,
            'discount_reason' => $approval->reason,
            'total_minor' => (int) $order->total_minor - $discountMinor,
            'lock_version' => $beforeVersion + 1,
        ])->save();
        $approval->forceFill(['status' => 'consumed', 'consumed_by_user_id' => $actor->id, 'consumed_at' => $now])->save();
        $this->audit($tenant, $branch, $actor, $order, 'order.discount_consumed', $requestId, [
            'approval_id' => $approval->id, 'discount_minor' => $discountMinor,
            'before_lock_version' => $beforeVersion, 'after_lock_version' => $beforeVersion + 1,
        ]);

        return ['created' => true, 'approval_id' => $approval->id, 'status' => 'consumed', 'order_id' => $order->id,
            'discount_minor' => $discountMinor, 'total_minor' => (int) $order->total_minor,
            'lock_version' => (int) $order->lock_version, 'currency' => $order->currency];
    }

    /** @return array{Tenant, User} */
    private function lockContext(Tenant $tenant, User $actor): array
    {
        $tenant = Tenant::query()->whereKey($tenant->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
        $actor = User::query()->whereKey($actor->id)->where('tenant_id', $tenant->id)->where('status', 'active')->lockForUpdate()->firstOrFail();

        return [$tenant, $actor];
    }

    private function branch(Tenant $tenant, Order $order): Branch
    {
        $branch = Branch::query()->whereKey($order->branch_id)->where('tenant_id', $tenant->id)->where('is_active', true)->lockForUpdate()->first();
        abort_unless($branch, 404);

        return $branch;
    }

    private function assertDraft(Order $order, int $expectedVersion, int $discountMinor): void
    {
        if ($order->status !== 'draft') {
            throw new HttpException(409, 'Only draft orders can receive a discount.');
        }
        if ((int) $order->lock_version !== $expectedVersion) {
            throw new HttpException(409, 'The order version is stale.');
        }
        if ((int) $order->total_minor < 1 || $discountMinor < 1 || $discountMinor >= (int) $order->total_minor || (int) $order->discount_minor !== 0) {
            throw new HttpException(409, 'The discount must leave a positive order total.');
        }
    }

    private function assertReconciled(Order $order): void
    {
        $subtotal = 0;
        $tax = 0;
        $total = 0;
        $grossTotal = 0;
        foreach ($order->items as $item) {
            $base = (int) $item->unit_price_minor * (int) $item->quantity;
            $gross = (int) $item->line_total_minor + (int) $item->discount_minor;
            if ($gross !== $base && $gross !== $base + (int) $item->tax_minor) {
                throw new HttpException(409, 'A persisted cart line does not reconcile with its price and tax.');
            }
            $subtotal += $base;
            $tax += (int) $item->tax_minor;
            $total += (int) $item->line_total_minor;
            $grossTotal += $gross;
        }
        if ($subtotal !== (int) $order->subtotal_minor
            || $tax !== (int) $order->tax_minor
            || $total !== (int) $order->total_minor
            || $grossTotal !== $total + (int) $order->discount_minor) {
            throw new HttpException(409, 'The persisted cart totals do not reconcile with its lines.');
        }
    }

    private function review(User $actor, Tenant $tenant, ApprovalRecord $approval, bool $approve, ?string $reason, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $tenant, $approval, $approve, $reason, $requestId): array {
            [$tenant, $actor] = $this->lockContext($tenant, $actor);
            $approval = ApprovalRecord::query()->whereKey($approval->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ApprovalRecordPolicy::class)->approve($actor, $approval), 403);
            $order = Order::query()->whereKey($approval->order_id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $branch = $this->branch($tenant, $order);
            if ($approval->status !== 'requested') {
                throw new HttpException(409, 'The discount approval was already reviewed.');
            }
            if ($approval->expires_at === null || $approval->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                throw new HttpException(409, 'The discount approval has expired.');
            }
            if ((int) $order->lock_version !== (int) $approval->expected_order_lock_version || $order->status !== 'draft') {
                throw new HttpException(409, 'The order changed after the discount request.');
            }
            $now = CarbonImmutable::now('UTC');
            $approval->forceFill($approve
                ? ['status' => 'approved', 'approved_by_user_id' => $actor->id, 'approved_at' => $now]
                : ['status' => 'rejected', 'rejected_by_user_id' => $actor->id, 'rejected_at' => $now, 'rejection_reason' => $reason])->save();
            $this->audit($tenant, $branch, $actor, $order, $approve ? 'order.discount_approved' : 'order.discount_rejected', $requestId, [
                'approval_id' => $approval->id, 'discount_minor' => (int) $approval->discount_minor,
                'reason' => $approve ? $approval->reason : $reason,
            ]);

            return ['created' => true, 'approval_id' => $approval->id, 'status' => $approval->status,
                'order_id' => $order->id, 'discount_minor' => (int) $approval->discount_minor,
                'expires_at' => $approval->expires_at?->toIso8601String()];
        });
    }

    private function fingerprint(Tenant $tenant, Branch $branch, Order $order, int $version, int $discountMinor, array $payload): string
    {
        $binding = [
            'tenant_id' => (int) $tenant->id, 'branch_id' => (int) $branch->id, 'order_id' => (int) $order->id,
            'expected_order_lock_version' => $version, 'discount_minor' => $discountMinor,
            'currency' => strtoupper((string) $order->currency), 'order' => $this->orderBinding($order),
        ];

        return hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
    }

    private function assertPayloadMatchesOrder(Order $order, array $payload): void
    {
        if ($order->items->isEmpty()) {
            throw new HttpException(409, 'The order has no persisted cart lines.');
        }

        $lines = $payload['lines'] ?? $payload['items'] ?? null;
        if (! is_array($lines)) {
            throw new HttpException(409, 'The discount approval payload does not match the persisted cart.');
        }
        $submitted = [];
        foreach ($lines as $line) {
            if (! is_array($line) || ! is_numeric($line['quantity'] ?? null)) {
                throw new HttpException(409, 'The discount approval payload does not match the persisted cart.');
            }
            $kind = isset($line['product_id']) ? 'product' : (isset($line['ticket_type_id']) ? 'ticket' : ($line['item_kind'] ?? null));
            $itemId = $line['product_id'] ?? $line['ticket_type_id'] ?? $line['item_id'] ?? null;
            if (! is_string($kind) || ! is_numeric($itemId)) {
                throw new HttpException(409, 'The discount approval payload does not match the persisted cart.');
            }
            $submitted[] = ['item_kind' => $kind, 'item_id' => (int) $itemId, 'quantity' => (int) $line['quantity']];
            if ($kind === 'ticket') {
                $submitted[array_key_last($submitted)] += [
                    'guardian_id' => (int) ($line['guardian_id'] ?? 0),
                    'child_id' => (int) ($line['child_id'] ?? 0),
                    'service_date' => (string) ($line['service_date'] ?? ''),
                ];
            }
        }
        $persisted = $order->items->sortBy('line_number')->values()->map(fn ($item): array => [
            'item_kind' => $item->item_kind,
            'item_id' => (int) ($item->product_id ?? $item->ticket_type_id ?? $item->session_id ?? 0),
            'quantity' => (int) $item->quantity,
            ...($item->item_kind === 'ticket' ? [
                'guardian_id' => (int) data_get($item->metadata_json, 'guardian_id', 0),
                'child_id' => (int) data_get($item->metadata_json, 'child_id', 0),
                'service_date' => (string) data_get($item->metadata_json, 'service_date', ''),
            ] : []),
        ])->all();
        if ($submitted !== $persisted) {
            throw new HttpException(409, 'The discount approval payload does not match the persisted cart.');
        }
    }

    /** @return array<string, mixed> */
    private function orderBinding(Order $order): array
    {
        return [
            'tenant_id' => (int) $order->tenant_id,
            'branch_id' => (int) $order->branch_id,
            'order_id' => (int) $order->id,
            'lock_version' => (int) $order->lock_version,
            'subtotal_minor' => (int) $order->subtotal_minor,
            'tax_minor' => (int) $order->tax_minor,
            'currency' => strtoupper((string) $order->currency),
            'items' => $order->items->sortBy('line_number')->values()->map(fn ($item): array => [
                'line_number' => (int) $item->line_number,
                'item_kind' => (string) $item->item_kind,
                'product_id' => $item->product_id === null ? null : (int) $item->product_id,
                'ticket_type_id' => $item->ticket_type_id === null ? null : (int) $item->ticket_type_id,
                'quantity' => (int) $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
                'tax_rate_bps' => (int) $item->tax_rate_bps,
                'tax_minor' => (int) $item->tax_minor,
                'line_total_minor' => (int) $item->line_total_minor,
                'currency' => strtoupper((string) $item->currency),
                ...($item->item_kind === 'ticket' ? [
                    'guardian_id' => (int) data_get($item->metadata_json, 'guardian_id', 0),
                    'child_id' => (int) data_get($item->metadata_json, 'child_id', 0),
                    'service_date' => (string) data_get($item->metadata_json, 'service_date', ''),
                ] : []),
            ])->all(),
        ];
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
        }
        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
    }

    private function audit(Tenant $tenant, Branch $branch, User $actor, Order $order, string $action, string $requestId, array $after): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id,
            'actor_type' => 'user', 'action' => $action, 'subject_type' => 'order', 'subject_id' => (string) $order->id,
            'outcome' => 'success', 'reason_code' => 'discount_approval', 'before_json' => json_encode([], JSON_THROW_ON_ERROR),
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'request_id' => $requestId, 'occurred_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
