<?php

namespace App\Actions;

use App\Models\ApprovalRecord;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Policies\PosPolicy;
use App\Support\TicketEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProcessOrdinaryPosOrder
{
    /** @param array<string, mixed> $data */
    public function create(User $actor, Tenant $tenant, array $data, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $tenant, $data, $requestId): array {
            [$actor, $tenant] = $this->lockContext($actor, $tenant);
            $branch = $this->branch($actor, $tenant, (int) $data['branch_id']);
            abort_unless(app(PosPolicy::class)->createOrder($actor, $branch), 403);
            $this->assertEgp($branch);
            $fingerprint = $this->createFingerprint($tenant, $data);
            $existing = Order::query()->with('items')->where('tenant_id', $tenant->id)->where('creation_idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->creation_fingerprint, $fingerprint)) {
                    throw new HttpException(409, 'The order idempotency key was already used with different data.');
                }

                return $this->orderResult($existing, false);
            }

            $guardianId = $data['guardian_id'] ?? null;
            if ($guardianId !== null) {
                Guardian::query()->where('tenant_id', $tenant->id)->whereKey($guardianId)->where('status', 'active')->lockForUpdate()->firstOrFail();
            }

            $now = CarbonImmutable::now('UTC');
            $resolved = $this->resolveLines($tenant, $branch, $data['items'], $guardianId);
            $order = Order::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'guardian_id' => $guardianId,
                'creation_idempotency_key' => $data['idempotency_key'],
                'creation_fingerprint' => $fingerprint,
                'status' => 'draft',
                'subtotal_minor' => $resolved['subtotal_minor'],
                'discount_minor' => 0,
                'tax_minor' => $resolved['tax_minor'],
                'total_minor' => $resolved['total_minor'],
                'paid_minor' => 0,
                'refunded_minor' => 0,
                'currency' => $branch->currency,
                'opened_by_user_id' => $actor->id,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($resolved['lines'] as $lineNumber => $line) {
                OrderItem::query()->create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'line_number' => $lineNumber + 1,
                    ...$line,
                ]);
            }
            $this->audit($tenant, $branch, $actor, $order, 'order.created', $requestId, [
                'status' => 'draft', 'lock_version' => 1, 'total_minor' => $resolved['total_minor'],
            ]);

            return $this->orderResult($order->fresh('items'), true);
        });
    }

    /** @param array<string, mixed> $data */
    public function pay(User $actor, Tenant $tenant, Order $order, array $data, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $tenant, $order, $data, $requestId): array {
            [$actor, $tenant] = $this->lockContext($actor, $tenant);
            $order = Order::query()->with('items')->whereKey($order->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
            $branch = $this->branch($actor, $tenant, (int) $order->branch_id);
            abort_unless(app(PosPolicy::class)->payOrder($actor, $branch), 403);
            $this->assertEgp($branch);
            $fingerprint = $this->paymentFingerprint($order, $data);

            $existing = Payment::query()->where('tenant_id', $tenant->id)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_fingerprint, $fingerprint) || (int) $existing->order_id !== (int) $order->id) {
                    throw new HttpException(409, 'The payment idempotency key was already used with different data.');
                }

                return $this->paidResult($order, $existing, false);
            }

            if ($order->status !== 'draft' || (int) $order->lock_version !== (int) $data['expected_order_lock_version']) {
                throw new HttpException(409, 'The order is no longer an editable draft.');
            }
            if (strtoupper((string) $order->currency) !== $data['currency']) {
                throw new HttpException(409, 'The cash amount must exactly match the order total.');
            }
            $this->assertCatalogStillSellable($order);
            $this->assertReconciled($order);
            if (! in_array('cash', (array) $branch->payment_methods, true)) {
                throw new HttpException(409, 'Cash payment is not enabled for this branch.');
            }

            if (! empty($data['approval_id'])) {
                $approval = app(DiscountApprovalAction::class);
                $approvalRecord = ApprovalRecord::query()->where('tenant_id', $tenant->id)->whereKey((int) $data['approval_id'])->lockForUpdate()->firstOrFail();
                if ((int) $approvalRecord->order_id !== (int) $order->id) {
                    throw new HttpException(409, 'The discount approval does not belong to this order.');
                }
                $approval->consumeLocked($actor, $tenant, $approvalRecord, (int) $data['expected_order_lock_version'], (int) $approvalRecord->discount_minor, $this->payloadFromOrder($order), $requestId);
                $order = Order::query()->with('items')->whereKey($order->id)->where('tenant_id', $tenant->id)->lockForUpdate()->firstOrFail();
                $this->assertReconciled($order);
                if ((int) $data['amount_minor'] !== (int) $order->total_minor) {
                    throw new HttpException(409, 'The cash amount must match the approved discounted total.');
                }
            } elseif ((int) $order->discount_minor > 0) {
                throw new HttpException(409, 'A discounted order requires its approval during payment.');
            } elseif ((int) $order->total_minor < 1 || (int) $data['amount_minor'] !== (int) $order->total_minor) {
                throw new HttpException(409, 'The cash amount must exactly match the order total.');
            }

            $now = CarbonImmutable::now('UTC');
            $payment = Payment::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'order_id' => $order->id,
                'method' => 'cash',
                'status' => 'posted',
                'amount_minor' => (int) $data['amount_minor'],
                'currency' => $data['currency'],
                'posted_by_user_id' => $actor->id,
                'posted_at' => $now,
                'idempotency_key' => $data['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $tickets = $this->issueTickets($tenant, $branch, $actor, $order, $requestId);
            $receiptNumber = $this->allocateReceiptNumber($tenant->id, $branch, $now);
            $receipt = $this->receipt($tenant, $order, $branch, $actor, $payment, $receiptNumber, $now);
            $beforeVersion = (int) $order->lock_version;
            $order->forceFill([
                'status' => 'paid', 'receipt_number' => $receiptNumber, 'receipt_snapshot_json' => $receipt,
                'receipt_issued_at' => $now, 'paid_minor' => $payment->amount_minor,
                'paid_by_user_id' => $actor->id, 'paid_at' => $now, 'lock_version' => $beforeVersion + 1,
            ])->save();
            $this->audit($tenant, $branch, $actor, $order, 'order.cash_paid', $requestId, [
                'status' => 'paid', 'payment_id' => $payment->id, 'receipt_number' => $receiptNumber,
                'amount_minor' => $payment->amount_minor, 'lock_version' => $beforeVersion + 1,
            ]);

            return [
                'created' => true, 'order_id' => $order->id, 'payment_id' => $payment->id,
                'status' => 'paid', 'receipt_number' => $receiptNumber, 'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency, 'lock_version' => $beforeVersion + 1,
                'verification_reference' => $receipt['verification_reference'], 'qr_payload' => $receipt['qr_payload'],
                'receipt' => $receipt, 'ticket_ids' => array_values($tickets),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function payloadFromOrder(Order $order): array
    {
        return ['items' => $order->items->sortBy('line_number')->values()->map(fn (OrderItem $item): array => [
            'item_kind' => $item->item_kind,
            'item_id' => (int) ($item->product_id ?? $item->ticket_type_id ?? 0),
            'quantity' => (int) $item->quantity,
            ...($item->item_kind === 'ticket' ? [
                'guardian_id' => (int) data_get($item->metadata_json, 'guardian_id', 0),
                'child_id' => (int) data_get($item->metadata_json, 'child_id', 0),
                'service_date' => (string) data_get($item->metadata_json, 'service_date', ''),
            ] : []),
        ])->all()];
    }

    /** @param array<string, mixed> $data */
    private function paymentFingerprint(Order $order, array $data): string
    {
        return hash('sha256', json_encode([
            'order_id' => (int) $order->id,
            'expected_order_lock_version' => (int) $data['expected_order_lock_version'],
            'amount_minor' => (int) $data['amount_minor'], 'currency' => $data['currency'],
            'method' => 'cash', 'approval_id' => $data['approval_id'] ?? null,
        ], JSON_THROW_ON_ERROR));
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

    /** @param array<string, mixed> $data */
    private function createFingerprint(Tenant $tenant, array $data): string
    {
        $guardianId = $data['guardian_id'] ?? null;
        $items = array_map(static function (array $item) use ($guardianId): array {
            $kind = $item['item_kind'] ?? (isset($item['product_id']) ? 'product' : 'ticket');

            return [
                'item_kind' => $kind,
                'item_id' => (int) ($item[$kind.'_id'] ?? $item['item_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'guardian_id' => $kind === 'ticket' ? (int) ($item['guardian_id'] ?? $guardianId ?? 0) : null,
                'child_id' => $kind === 'ticket' ? (int) ($item['child_id'] ?? 0) : null,
                'service_date' => $kind === 'ticket' ? (string) ($item['service_date'] ?? '') : null,
            ];
        }, $data['items']);

        return hash('sha256', json_encode(['tenant_id' => $tenant->id, 'branch_id' => (int) $data['branch_id'], 'guardian_id' => $data['guardian_id'] ?? null, 'items' => $items], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    private function resolveLines(Tenant $tenant, Branch $branch, array $items, mixed $guardianId): array
    {
        $resolved = [];
        $subtotal = 0;
        $taxTotal = 0;
        foreach ($items as $input) {
            $kind = $input['item_kind'] ?? (isset($input['product_id']) || ($input['item_kind'] ?? null) === 'product' ? 'product' : 'ticket');
            $quantity = (int) ($input['quantity'] ?? 0);
            if ($quantity < 1 || $quantity > 1000 || ! in_array($kind, ['product', 'ticket'], true)) {
                throw ValidationException::withMessages(['items' => 'Each item must have a supported kind and quantity.']);
            }
            $itemId = (int) ($input[$kind === 'product' ? 'product_id' : 'ticket_type_id'] ?? $input['item_id'] ?? 0);
            if ($itemId < 1) {
                throw ValidationException::withMessages(['items' => 'Each item must reference a catalog identifier.']);
            }
            $catalog = $kind === 'product'
                ? Product::query()->where('tenant_id', $tenant->id)->where(function ($query) use ($branch): void {
                    $query->where('branch_id', $branch->id)->orWhereNull('branch_id');
                })->where('status', 'active')->whereKey($itemId)->lockForUpdate()->firstOrFail()
                : TicketType::query()->with('pricingRule')->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('status', 'active')->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $rule = $kind === 'ticket' ? $catalog->pricingRule : null;
            if ($kind === 'ticket' && (! $rule || $rule->status !== 'active')) {
                throw new HttpException(409, 'The ticket pricing is unavailable.');
            }
            $unit = (int) $catalog->price_minor;
            $currency = strtoupper((string) ($kind === 'product' ? $catalog->currency : $catalog->currency));
            $rate = (int) ($kind === 'product' ? $catalog->tax_rate_bps : $rule->tax_rate_bps);
            $mode = (string) ($kind === 'product' ? $catalog->tax_mode : $rule->tax_mode);
            if ($currency !== strtoupper((string) $branch->currency) || $unit < 1) {
                throw new HttpException(409, 'The item currency or price is unavailable for this branch.');
            }
            $base = $unit * $quantity;
            $tax = $this->tax($base, $rate, $mode);
            $subtotal += $base;
            $taxTotal += $tax;
            $metadata = ['catalog_lock_version' => (int) $catalog->lock_version];
            if ($kind === 'ticket') {
                $lineGuardian = (int) ($input['guardian_id'] ?? $guardianId ?? 0);
                $lineChild = (int) ($input['child_id'] ?? 0);
                $serviceDate = $input['service_date'] ?? null;
                if ($lineGuardian < 1 || $lineChild < 1 || ! is_string($serviceDate) || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $serviceDate)) {
                    throw ValidationException::withMessages(['items' => 'Ticket items require guardian, child and service date.']);
                }
                if ($guardianId !== null && (int) $guardianId !== $lineGuardian) {
                    throw ValidationException::withMessages(['guardian_id' => 'All ticket items must use the order guardian.']);
                }
                if (! TicketEligibility::familyQuery($tenant)
                    ->where('guardian_child.guardian_id', $lineGuardian)
                    ->where('guardian_child.child_id', $lineChild)
                    ->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['items' => 'The ticket family assignment is unavailable.']);
                }
                $this->serviceWindow($tenant, $branch, $serviceDate);
                $metadata = [
                    'catalog_lock_version' => (int) ($catalog->lock_version ?? 0),
                    'guardian_id' => $lineGuardian, 'child_id' => $lineChild, 'service_date' => $serviceDate,
                    'pricing_rule_id' => (int) $rule->id, 'pricing_rule_version' => (int) $rule->version,
                    'tax_rate_bps' => $rate, 'tax_mode' => $mode, 'branch_timezone' => $branch->timezone,
                ];
            }
            $resolved[] = [
                'item_kind' => $kind, 'product_id' => $kind === 'product' ? $catalog->id : null,
                'ticket_type_id' => $kind === 'ticket' ? $catalog->id : null, 'session_id' => null,
                'description_snapshot' => (string) $catalog->name, 'quantity' => $quantity,
                'unit_price_minor' => $unit, 'discount_minor' => 0, 'tax_rate_bps' => $rate,
                'tax_minor' => $tax, 'line_total_minor' => $mode === 'inclusive' ? $base : $base + $tax,
                'currency' => $currency, 'metadata_json' => $metadata,
            ];
        }
        if ($subtotal < 1 || $subtotal + $taxTotal < 1) {
            throw new HttpException(409, 'Zero-total orders are not available.');
        }

        return ['lines' => $resolved, 'subtotal_minor' => $subtotal, 'tax_minor' => $taxTotal, 'total_minor' => array_sum(array_column($resolved, 'line_total_minor'))];
    }

    private function tax(int $base, int $rate, string $mode): int
    {
        return $mode === 'inclusive'
            ? intdiv($base * $rate + intdiv(10000 + $rate, 2), 10000 + $rate)
            : intdiv($base * $rate + 5000, 10000);
    }

    private function assertCatalogStillSellable(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->item_kind === 'product') {
                $product = Product::query()->where('tenant_id', $order->tenant_id)->where(function ($query) use ($order): void {
                    $query->where('branch_id', $order->branch_id)->orWhereNull('branch_id');
                })->whereKey($item->product_id)->lockForUpdate()->first();
                if (! $product || $product->status !== 'active' || (int) $product->lock_version !== (int) data_get($item->metadata_json, 'catalog_lock_version')) {
                    throw new HttpException(409, 'A catalog item changed after the draft was created.');
                }
            } elseif ($item->item_kind === 'ticket') {
                $type = TicketType::query()->where('tenant_id', $order->tenant_id)->where('branch_id', $order->branch_id)->whereKey($item->ticket_type_id)->lockForUpdate()->first();
                if (! $type || $type->status !== 'active' || (int) $type->lock_version !== (int) data_get($item->metadata_json, 'catalog_lock_version')) {
                    throw new HttpException(409, 'A catalog item changed after the draft was created.');
                }
                $rule = $type->pricingRule()->lockForUpdate()->first();
                if (! $rule || $rule->status !== 'active'
                    || (int) $rule->version !== (int) data_get($item->metadata_json, 'pricing_rule_version')) {
                    throw new HttpException(409, 'The ticket pricing is unavailable.');
                }
            }
        }
    }

    /** @return list<int> */
    private function issueTickets(Tenant $tenant, Branch $branch, User $actor, Order $order, string $requestId): array
    {
        $ids = [];
        foreach ($order->items->where('item_kind', 'ticket') as $item) {
            $metadata = is_array($item->metadata_json) ? $item->metadata_json : [];
            $type = TicketType::query()->with('pricingRule')->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('status', 'active')->whereKey($item->ticket_type_id)->lockForUpdate()->firstOrFail();
            $rule = $type->pricingRule;
            if (! $rule || $rule->status !== 'active') {
                throw new HttpException(409, 'The ticket pricing is unavailable.');
            }
            $family = TicketEligibility::familyQuery($tenant)->where('guardian_child.guardian_id', (int) $metadata['guardian_id'])->where('guardian_child.child_id', (int) $metadata['child_id'])->lockForUpdate()->first();
            if (! $family) {
                throw ValidationException::withMessages(['items' => 'The ticket family assignment is unavailable.']);
            }
            [$validFrom, $validUntil] = $this->serviceWindow($tenant, $branch, (string) $metadata['service_date']);
            for ($index = 0; $index < (int) $item->quantity; $index++) {
                $payload = 'pnx_'.Str::random(48);
                $now = CarbonImmutable::now('UTC');
                $ticket = Ticket::query()->create([
                    'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'ticket_type_id' => $type->id,
                    'guardian_id' => (int) $metadata['guardian_id'], 'child_id' => (int) $metadata['child_id'],
                    'service_date' => $metadata['service_date'], 'status' => 'issued', 'code_hash' => hash('sha256', $payload),
                    'code_payload_encrypted' => $payload, 'display_code' => $this->uniqueDisplayCode($tenant),
                    'price_minor' => $type->price_minor, 'currency' => $type->currency,
                    'price_snapshot_json' => [
                        'ticket_type_id' => (int) $type->id, 'pricing_rule_id' => (int) $rule->id,
                        'pricing_rule_version' => (int) $rule->version, 'base_duration_seconds' => (int) $rule->base_duration_seconds,
                        'grace_period_seconds' => (int) $rule->grace_period_seconds, 'overtime_unit_seconds' => (int) $rule->overtime_unit_seconds,
                        'overtime_price_minor' => (int) $rule->overtime_price_minor, 'price_minor' => (int) $type->price_minor,
                        'currency' => $type->currency, 'tax_rate_bps' => (int) $rule->tax_rate_bps,
                        'tax_mode' => $rule->tax_mode, 'branch_timezone' => $branch->timezone,
                    ],
                    'issued_at' => $now, 'valid_from' => $validFrom, 'valid_until' => $validUntil,
                    'uses_count' => 0, 'max_uses' => $type->max_uses, 'idempotency_key' => (string) Str::uuid(),
                    'issue_fingerprint' => hash('sha256', json_encode(['order_id' => $order->id, 'line' => $item->line_number, 'unit' => $index], JSON_THROW_ON_ERROR)),
                    'issued_by_user_id' => $actor->id, 'lock_version' => 1,
                ]);
                $ids[] = (int) $ticket->id;
                $metadata['ticket_ids'][] = (int) $ticket->id;
                $metadata['ticket_id'] ??= (int) $ticket->id;
                $this->audit($tenant, $branch, $actor, $order, 'ticket.issued_from_order', $requestId, ['ticket_id' => $ticket->id, 'order_id' => $order->id]);
            }
            $item->forceFill(['metadata_json' => $metadata])->save();
        }

        return $ids;
    }

    private function receipt(Tenant $tenant, Order $order, Branch $branch, User $actor, Payment $payment, string $number, CarbonImmutable $now): array
    {
        $seller = User::query()->where('tenant_id', $order->tenant_id)->whereKey($order->opened_by_user_id)->where('status', 'active')->first();
        if ($seller === null) {
            throw new HttpException(409, 'The order seller identity is missing.');
        }
        $verificationReference = (string) Str::uuid();
        $items = $order->items->sortBy('line_number')->values()->map(function (OrderItem $item): array {
            $metadata = is_array($item->metadata_json) ? $item->metadata_json : [];

            return [
                'line_number' => (int) $item->line_number, 'item_kind' => $item->item_kind, 'description' => $item->description_snapshot,
                'quantity' => (int) $item->quantity, 'unit_price_minor' => (int) $item->unit_price_minor,
                'discount_minor' => (int) $item->discount_minor, 'tax_rate_bps' => (int) $item->tax_rate_bps,
                'tax_minor' => (int) $item->tax_minor, 'line_total_minor' => (int) $item->line_total_minor,
                'currency' => $item->currency, 'ticket_id' => $metadata['ticket_id'] ?? null, 'ticket_ids' => $metadata['ticket_ids'] ?? [],
            ];
        })->all();

        return [
            'order_id' => (int) $order->id, 'branch_id' => (int) $branch->id, 'receipt_number' => $number,
            'status' => 'paid', 'issued_at' => $now->toIso8601String(), 'cashier_user_id' => (int) $actor->id,
            'cashier_name' => $actor->name, 'seller_user_id' => (int) $seller->id, 'seller_name' => $tenant->legal_name ?: $tenant->name,
            'branch_name' => $branch->name, 'items' => $items,
            'branch_timezone' => $branch->timezone,
            'subtotal_minor' => (int) $order->subtotal_minor, 'discount_minor' => (int) $order->discount_minor,
            'tax_minor' => (int) $order->tax_minor, 'total_minor' => (int) $order->total_minor,
            'paid_minor' => (int) $payment->amount_minor, 'refunded_minor' => 0, 'currency' => $payment->currency,
            'payment_method' => 'cash', 'payment_reference_masked' => null,
            'verification_reference' => $verificationReference, 'qr_payload' => 'PNR1|'.$verificationReference,
        ];
    }

    /** @return array<string, mixed> */
    private function paidResult(Order $order, Payment $payment, bool $created): array
    {
        $receipt = is_array($order->receipt_snapshot_json) ? $order->receipt_snapshot_json : json_decode((string) $order->receipt_snapshot_json, true);
        if (! is_array($receipt)) {
            throw new HttpException(409, 'The stored receipt snapshot is invalid.');
        }

        return [
            'created' => $created, 'order_id' => (int) $order->id, 'payment_id' => (int) $payment->id,
            'status' => (string) $order->status, 'receipt_number' => (string) $order->receipt_number,
            'amount_minor' => (int) $payment->amount_minor, 'currency' => (string) $payment->currency,
            'lock_version' => (int) $order->lock_version, 'receipt' => $receipt,
            'verification_reference' => (string) ($receipt['verification_reference'] ?? ''),
            'qr_payload' => (string) ($receipt['qr_payload'] ?? ''),
            'ticket_ids' => collect($receipt['items'] ?? [])->flatMap(fn (array $item): array => $item['ticket_ids'] ?? [])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function orderResult(Order $order, bool $created): array
    {
        return [
            'created' => $created, 'order_id' => (int) $order->id, 'status' => (string) $order->status,
            'lock_version' => (int) $order->lock_version, 'currency' => (string) $order->currency,
            'subtotal_minor' => (int) $order->subtotal_minor, 'discount_minor' => (int) $order->discount_minor,
            'tax_minor' => (int) $order->tax_minor, 'total_minor' => (int) $order->total_minor,
            'items' => $order->items->sortBy('line_number')->values()->map(fn (OrderItem $item): array => [
                'id' => (int) $item->id, 'line_number' => (int) $item->line_number, 'item_kind' => $item->item_kind,
                'product_id' => $item->product_id === null ? null : (int) $item->product_id,
                'ticket_type_id' => $item->ticket_type_id === null ? null : (int) $item->ticket_type_id,
                'quantity' => (int) $item->quantity, 'unit_price_minor' => (int) $item->unit_price_minor,
                'tax_minor' => (int) $item->tax_minor, 'line_total_minor' => (int) $item->line_total_minor,
                'currency' => $item->currency,
            ])->all(),
        ];
    }

    /** @return array{User, Tenant} */
    private function lockContext(User $actor, Tenant $tenant): array
    {
        $tenant = Tenant::query()->whereKey($tenant->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
        $actor = User::query()->whereKey($actor->id)->where('tenant_id', $tenant->id)->where('status', 'active')->lockForUpdate()->firstOrFail();

        return [$actor, $tenant];
    }

    private function branch(User $actor, Tenant $tenant, int $branchId): Branch
    {
        return Branch::query()->whereKey($branchId)->where('tenant_id', $tenant->id)->where('is_active', true)
            ->whereIn('id', $actor->accessibleBranches()->select('branches.id'))->lockForUpdate()->firstOrFail();
    }

    private function assertEgp(Branch $branch): void
    {
        if (strtoupper((string) $branch->currency) !== 'EGP') {
            throw new HttpException(409, 'Ordinary cash POS payments are limited to EGP.');
        }
    }

    private function uniqueDisplayCode(Tenant $tenant): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = 'PN-';
            for ($index = 0; $index < 8; $index++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            if (! Ticket::query()->where('tenant_id', $tenant->id)->where('display_code', $code)->exists()) {
                return $code;
            }
        }
        throw new HttpException(503, 'A ticket code could not be allocated.');
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function serviceWindow(Tenant $tenant, Branch $branch, string $serviceDate): array
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $serviceDate, $branch->timezone);
        if (! $date || $date->format('Y-m-d') !== $serviceDate || $date->lessThan(CarbonImmutable::now($branch->timezone)->startOfDay())) {
            throw ValidationException::withMessages(['service_date' => 'The service date is unavailable.']);
        }
        $hours = DB::table('branch_opening_hours')->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('weekday', $date->isoWeekday())->lockForUpdate()->first();
        if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            throw ValidationException::withMessages(['service_date' => 'The branch is closed on that service date.']);
        }
        $from = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $serviceDate.' '.$hours->opens_at, $branch->timezone);
        $until = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $serviceDate.' '.$hours->closes_at, $branch->timezone);
        if (! $from || ! $until || ! $until->greaterThan($from) || $until->lessThanOrEqualTo(CarbonImmutable::now($branch->timezone))) {
            throw ValidationException::withMessages(['service_date' => 'The service date is unavailable.']);
        }

        return [$from->utc(), $until->utc()];
    }

    private function allocateReceiptNumber(int $tenantId, Branch $branch, CarbonImmutable $now): string
    {
        $year = (int) $now->setTimezone($branch->timezone)->format('Y');
        $name = 'receipt-'.$year;
        $sequence = DB::table('branch_sequences')->where('tenant_id', $tenantId)->where('branch_id', $branch->id)->where('sequence_name', $name)->lockForUpdate()->first();
        if ($sequence === null) {
            DB::table('branch_sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'branch_id' => $branch->id, 'sequence_name' => $name, 'current_value' => 0, 'prefix' => $branch->receipt_prefix ?: ($branch->code ?: 'PN'), 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $sequence = DB::table('branch_sequences')->where('tenant_id', $tenantId)->where('branch_id', $branch->id)->where('sequence_name', $name)->lockForUpdate()->firstOrFail();
        }
        $next = (int) $sequence->current_value + 1;
        DB::table('branch_sequences')->where('tenant_id', $tenantId)->where('id', $sequence->id)->update(['current_value' => $next, 'lock_version' => (int) $sequence->lock_version + 1, 'updated_at' => $now]);

        return sprintf('%s-%d-%06d', strtoupper((string) $sequence->prefix), $year, $next);
    }

    private function audit(Tenant $tenant, Branch $branch, User $actor, Model $subject, string $action, string $requestId, array $after): void
    {
        DB::table('audit_logs')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id, 'actor_type' => 'user', 'action' => $action, 'subject_type' => 'order', 'subject_id' => (string) $subject->getKey(), 'outcome' => 'success', 'reason_code' => 'cash_pos', 'before_json' => null, 'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'request_id' => $requestId, 'occurred_at' => CarbonImmutable::now('UTC')]);
    }
}
