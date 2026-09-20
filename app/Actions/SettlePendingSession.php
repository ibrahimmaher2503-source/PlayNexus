<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\PlaySession;
use App\Models\PlaySessionEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PlaySessionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SettlePendingSession
{
    /**
     * @return array{created: bool, session_id: int, order_id: int, payment_id: int, status: string, receipt_number: string, amount_minor: int, currency: string, lock_version: int, receipt: array<string, mixed>}
     */
    public function handle(
        User $actor,
        Tenant $tenant,
        PlaySession $session,
        int $expectedLockVersion,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        string $requestId,
    ): array {
        $currency = strtoupper($currency);

        return DB::transaction(function () use (
            $actor,
            $tenant,
            $session,
            $expectedLockVersion,
            $amountMinor,
            $currency,
            $idempotencyKey,
            $requestId,
        ): array {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSession = PlaySession::query()
                ->whereKey($session->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $branch = Branch::query()
                ->whereKey($lockedSession->branch_id)
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $seller = $lockedSession->checkout_verified_by_user_id === null
                ? null
                : User::query()
                    ->whereKey($lockedSession->checkout_verified_by_user_id)
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->first();
            if ($seller === null) {
                throw new HttpException(409, 'The checkout seller identity is missing.');
            }

            if (! app(PlaySessionPolicy::class)->settle($lockedActor, $lockedSession)) {
                throw new HttpException(403, 'This cashier cannot settle the session.');
            }

            $fingerprint = $this->fingerprint($lockedSession, $expectedLockVersion, $amountMinor, $currency);
            $existingPayment = DB::table('payments')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existingPayment !== null) {
                if (! hash_equals((string) $existingPayment->request_fingerprint, $fingerprint)) {
                    throw new HttpException(409, 'The idempotency key was already used with different data.');
                }

                $existingOrder = DB::table('orders')
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->where('id', $existingPayment->order_id)
                    ->lockForUpdate()
                    ->first();
                if ($existingOrder === null || (int) $existingOrder->session_id !== (int) $lockedSession->getKey()) {
                    throw new HttpException(409, 'The idempotency key belongs to another session.');
                }

                return $this->resultFromExisting($lockedSession, $existingOrder, $existingPayment);
            }

            if (! in_array('cash', (array) $branch->payment_methods, true)) {
                throw new HttpException(409, 'Cash payment is not enabled for this branch.');
            }

            $quote = $this->decodeSnapshot($lockedSession->checkout_snapshot_json);
            $amountDue = (int) ($lockedSession->checkout_amount_due_minor ?? -1);
            $snapshotAmount = (int) ($quote['total_minor'] ?? -1);
            $snapshotCurrency = $quote['currency'] ?? null;
            if ($lockedSession->status !== 'pending_payment') {
                throw new HttpException(409, 'The session is no longer awaiting cash settlement.');
            }
            if ((int) $lockedSession->lock_version !== $expectedLockVersion) {
                throw new HttpException(409, 'The session version is stale.');
            }
            if ($amountDue < 1 || $snapshotAmount !== $amountDue || $snapshotCurrency !== $currency || $amountMinor !== $amountDue) {
                throw new HttpException(409, 'The cash amount does not match the frozen checkout.');
            }
            if ($lockedSession->checkout_verified_at === null || $lockedSession->checkout_verification_method === null) {
                throw new HttpException(409, 'The checkout verification is missing.');
            }
            $finalFacts = [
                'elapsed_seconds' => (int) ($quote['elapsed_seconds'] ?? 0),
                'excluded_pause_seconds' => (int) ($quote['excluded_pause_seconds'] ?? 0),
                'billable_seconds' => (int) ($quote['billable_seconds'] ?? $quote['elapsed_seconds'] ?? 0),
                'subtotal_minor' => (int) ($quote['subtotal_minor'] ?? 0),
                'tax_minor' => (int) ($quote['tax_minor'] ?? 0),
                'adjustment_minor' => (int) ($quote['adjustment_minor'] ?? 0),
                'total_minor' => $amountDue,
                'currency' => $currency,
            ];

            $existingOrder = DB::table('orders')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('session_id', $lockedSession->getKey())
                ->lockForUpdate()
                ->first();
            if ($existingOrder !== null) {
                throw new HttpException(409, 'The session already has a settlement order.');
            }

            $now = CarbonImmutable::now('UTC');
            $orderId = DB::table('orders')->insertGetId([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $branch->getKey(),
                'guardian_id' => $lockedSession->guardian_id,
                'session_id' => $lockedSession->getKey(),
                'status' => 'draft',
                'subtotal_minor' => (int) ($quote['subtotal_minor'] ?? 0),
                'discount_minor' => 0,
                'tax_minor' => (int) ($quote['tax_minor'] ?? 0),
                'total_minor' => $amountDue,
                'paid_minor' => 0,
                'refunded_minor' => 0,
                'currency' => $currency,
                'opened_by_user_id' => $seller->getKey(),
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $line = [
                'tenant_id' => $lockedTenant->getKey(),
                'order_id' => $orderId,
                'line_number' => 1,
                'item_kind' => 'session',
                'session_id' => $lockedSession->getKey(),
                'description_snapshot' => 'Play session',
                'quantity' => 1,
                'unit_price_minor' => (int) ($quote['subtotal_minor'] ?? 0),
                'discount_minor' => 0,
                'tax_rate_bps' => (int) ($quote['tax_rate_bps'] ?? 0),
                'tax_minor' => (int) ($quote['tax_minor'] ?? 0),
                'line_total_minor' => $amountDue,
                'currency' => $currency,
                'metadata_json' => json_encode(['checkout_snapshot' => $quote, 'session_facts' => $finalFacts], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            DB::table('order_items')->insert($line);

            $paymentId = DB::table('payments')->insertGetId([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $branch->getKey(),
                'order_id' => $orderId,
                'method' => 'cash',
                'status' => 'posted',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'posted_by_user_id' => $lockedActor->getKey(),
                'posted_at' => $now,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $receiptNumber = $this->allocateReceiptNumber($lockedTenant->getKey(), $branch, $now);
            $lineSnapshot = [[
                'line_number' => 1,
                'item_kind' => 'session',
                'description' => $line['description_snapshot'],
                'quantity' => 1,
                'unit_price_minor' => $line['unit_price_minor'],
                'discount_minor' => 0,
                'tax_rate_bps' => $line['tax_rate_bps'],
                'tax_minor' => $line['tax_minor'],
                'line_total_minor' => $line['line_total_minor'],
                'currency' => $currency,
            ]];
            $receipt = [
                'order_id' => $orderId,
                'session_id' => $lockedSession->getKey(),
                'branch_id' => $branch->getKey(),
                'receipt_number' => $receiptNumber,
                'status' => 'paid',
                'issued_at' => $now->toIso8601String(),
                'cashier_user_id' => $lockedActor->getKey(),
                'cashier_name' => $lockedActor->name,
                'seller_user_id' => $seller->getKey(),
                'seller_name' => $lockedTenant->legal_name ?: $lockedTenant->name,
                'branch_name' => $branch->name,
                'branch_timezone' => $branch->timezone,
                'items' => $lineSnapshot,
                'subtotal_minor' => $line['unit_price_minor'],
                'discount_minor' => 0,
                'tax_minor' => $line['tax_minor'],
                'total_minor' => $amountDue,
                'paid_minor' => $amountMinor,
                'refunded_minor' => 0,
                'currency' => $currency,
                'payment_method' => 'cash',
                'payment_reference_masked' => null,
                'session_facts' => $finalFacts,
            ];
            $receipt['verification_reference'] = (string) Str::uuid();
            $receipt['qr_payload'] = 'PNR1|'.$receipt['verification_reference'];
            DB::table('orders')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('id', $orderId)
                ->update([
                    'status' => 'paid',
                    'receipt_number' => $receiptNumber,
                    'receipt_snapshot_json' => json_encode($receipt, JSON_THROW_ON_ERROR),
                    'receipt_issued_at' => $now,
                    'paid_minor' => $amountMinor,
                    'paid_by_user_id' => $lockedActor->getKey(),
                    'paid_at' => $now,
                    'lock_version' => 2,
                    'updated_at' => $now,
                ]);

            $beforeVersion = (int) $lockedSession->lock_version;
            DB::table('play_sessions')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('id', $lockedSession->getKey())
                ->update([
                    'status' => 'completed',
                    'ended_at' => $now,
                    'lock_version' => $beforeVersion + 1,
                    'updated_at' => $now,
                ]);
            PlaySessionEvent::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'event_type' => 'completed',
                'from_status' => 'pending_payment',
                'to_status' => 'completed',
                'reason_code' => 'cash_payment',
                'actor_user_id' => $lockedActor->getKey(),
                'occurred_at' => $now,
                'metadata_json' => [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'receipt_number' => $receiptNumber,
                    'final_facts' => $finalFacts,
                ],
                'request_id' => $requestId,
            ]);
            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $branch->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'session.cash_settled',
                'subject_type' => 'play_session',
                'subject_id' => (string) $lockedSession->getKey(),
                'outcome' => 'success',
                'reason_code' => 'cash_payment',
                'before_json' => json_encode([
                    'status' => 'pending_payment',
                    'lock_version' => $beforeVersion,
                    'amount_due_minor' => $amountDue,
                    'currency' => $currency,
                ], JSON_THROW_ON_ERROR),
                'after_json' => json_encode([
                    'status' => 'completed',
                    'lock_version' => $beforeVersion + 1,
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'receipt_number' => $receiptNumber,
                    'final_facts' => $finalFacts,
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'occurred_at' => $now,
            ]);

            return [
                'created' => true,
                'session_id' => (int) $lockedSession->getKey(),
                'order_id' => (int) $orderId,
                'payment_id' => (int) $paymentId,
                'status' => 'completed',
                'receipt_number' => $receiptNumber,
                'verification_reference' => $receipt['verification_reference'],
                'qr_payload' => $receipt['qr_payload'],
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'lock_version' => $beforeVersion + 1,
                'receipt' => $receipt,
            ];
        });
    }

    /** @return array<string, mixed> */
    private function decodeSnapshot(mixed $snapshot): array
    {
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        if (! is_array($snapshot)) {
            throw new HttpException(409, 'The frozen checkout snapshot is invalid.');
        }

        foreach (['elapsed_seconds', 'subtotal_minor', 'tax_minor', 'total_minor', 'tax_rate_bps'] as $key) {
            if (! array_key_exists($key, $snapshot) || ! is_int($snapshot[$key]) || $snapshot[$key] < 0) {
                throw new HttpException(409, 'The frozen checkout snapshot is incomplete.');
            }
        }
        foreach (['excluded_pause_seconds', 'billable_seconds'] as $key) {
            if (array_key_exists($key, $snapshot) && (! is_int($snapshot[$key]) || $snapshot[$key] < 0)) {
                throw new HttpException(409, 'The frozen checkout snapshot is invalid.');
            }
        }
        if (array_key_exists('adjustment_minor', $snapshot) && ! is_int($snapshot['adjustment_minor'])) {
            throw new HttpException(409, 'The frozen checkout snapshot is invalid.');
        }
        if (! is_string($snapshot['tax_mode'] ?? null) || ! in_array($snapshot['tax_mode'], ['exclusive', 'inclusive'], true)) {
            throw new HttpException(409, 'The frozen checkout snapshot is incomplete.');
        }
        if (! is_string($snapshot['currency'] ?? null) || preg_match('/\A[A-Z]{3}\z/D', $snapshot['currency']) !== 1) {
            throw new HttpException(409, 'The frozen checkout snapshot is incomplete.');
        }

        return $snapshot;
    }

    private function fingerprint(PlaySession $session, int $expectedLockVersion, int $amountMinor, string $currency): string
    {
        return hash('sha256', json_encode([
            'session_id' => (int) $session->getKey(),
            'expected_lock_version' => $expectedLockVersion,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'method' => 'cash',
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{created: bool, session_id: int, order_id: int, payment_id: int, status: string, receipt_number: string, amount_minor: int, currency: string, lock_version: int, receipt: array<string, mixed>} */
    private function resultFromExisting(PlaySession $session, object $order, object $payment): array
    {
        $receipt = is_string($order->receipt_snapshot_json)
            ? json_decode($order->receipt_snapshot_json, true)
            : $order->receipt_snapshot_json;
        if (! is_array($receipt)) {
            throw new HttpException(409, 'The stored receipt snapshot is invalid.');
        }

        return [
            'created' => false,
            'session_id' => (int) $session->getKey(),
            'order_id' => (int) $order->id,
            'payment_id' => (int) $payment->id,
            'status' => (string) $session->status,
            'receipt_number' => (string) $order->receipt_number,
            'verification_reference' => (string) ($receipt['verification_reference'] ?? ''),
            'qr_payload' => (string) ($receipt['qr_payload'] ?? ''),
            'amount_minor' => (int) $payment->amount_minor,
            'currency' => (string) $payment->currency,
            'lock_version' => (int) $session->lock_version,
            'receipt' => $receipt,
        ];
    }

    private function allocateReceiptNumber(int $tenantId, Branch $branch, CarbonImmutable $now): string
    {
        $year = (int) $now->setTimezone($branch->timezone)->format('Y');
        $sequenceName = 'receipt-'.$year;
        $sequence = DB::table('branch_sequences')
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branch->getKey())
            ->where('sequence_name', $sequenceName)
            ->lockForUpdate()
            ->first();
        if ($sequence === null) {
            DB::table('branch_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'branch_id' => $branch->getKey(),
                'sequence_name' => $sequenceName,
                'current_value' => 0,
                'prefix' => $branch->receipt_prefix ?: ($branch->code ?: 'PN'),
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sequence = DB::table('branch_sequences')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branch->getKey())
                ->where('sequence_name', $sequenceName)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $next = (int) $sequence->current_value + 1;
        DB::table('branch_sequences')
            ->where('tenant_id', $tenantId)
            ->where('id', $sequence->id)
            ->update([
                'current_value' => $next,
                'lock_version' => (int) $sequence->lock_version + 1,
                'updated_at' => $now,
            ]);

        return sprintf('%s-%d-%06d', strtoupper((string) $sequence->prefix), $year, $next);
    }
}
