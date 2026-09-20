<?php

namespace Database\Seeders;

use App\Actions\CollectOperationalNotifications;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class M6PilotSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('The synthetic M6 pilot seed is limited to local and testing environments.');
        }

        $this->call(DatabaseSeeder::class);
        $tenant = Tenant::query()->where('internal_identifier', 'demo-nile-alpha')->firstOrFail();
        $branch = $tenant->branches()->where('code', 'ALPHA-MAIN')->firstOrFail();
        $owner = $tenant->users()->where('email', 'demo.alpha.owner@playnexus.test')->firstOrFail();
        $now = now('UTC');

        DB::transaction(function () use ($tenant, $branch, $owner, $now): void {
            $guardian = Guardian::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'phone_e164' => '+201000000001'],
                ['full_name' => '[DEMO] Pilot Guardian', 'preferred_locale' => 'ar', 'status' => 'active', 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id],
            );
            $child = Child::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'full_name' => '[DEMO] Pilot Child'],
                ['date_of_birth' => $now->copy()->subYears(6)->toDateString(), 'status' => 'active', 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id],
            );
            DB::table('guardian_child')->updateOrInsert(
                ['tenant_id' => $tenant->id, 'guardian_id' => $guardian->id, 'child_id' => $child->id],
                ['relationship_type' => 'legal_guardian', 'can_consent' => true, 'can_check_out' => true, 'is_primary' => true, 'verification_method' => 'in_person', 'verified_at' => $now, 'verified_by_user_id' => $owner->id, 'is_active' => true, 'created_by_user_id' => $owner->id, 'updated_by_user_id' => $owner->id, 'created_at' => $now, 'updated_at' => $now],
            );
            $rule = PricingRule::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => 'M6-PILOT', 'version' => 1],
                ['name' => '[DEMO] Pilot 60 minutes', 'billing_mode' => 'fixed_duration', 'base_duration_seconds' => 3600, 'base_price_minor' => 15000, 'grace_period_seconds' => 600, 'overtime_unit_seconds' => 1800, 'overtime_price_minor' => 7500, 'currency' => 'EGP', 'tax_rate_bps' => 0, 'tax_mode' => 'exclusive', 'status' => 'active', 'created_by_user_id' => $owner->id],
            );
            $type = TicketType::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => 'M6-PILOT'],
                ['pricing_rule_id' => $rule->id, 'name' => '[DEMO] Pilot ticket', 'price_minor' => 15000, 'currency' => 'EGP', 'max_uses' => 1, 'status' => 'active', 'created_by_user_id' => $owner->id],
            );
            $completedTicket = $this->ticket($tenant->id, $branch->id, $type->id, $guardian->id, $child->id, $owner->id, 'PN-M6-DONE', $now);
            $activeTicket = $this->ticket($tenant->id, $branch->id, $type->id, $guardian->id, $child->id, $owner->id, 'PN-M6-DUE', $now);
            $completed = PlaySession::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'ticket_id' => $completedTicket->id],
                ['branch_id' => $branch->id, 'child_id' => $child->id, 'guardian_id' => $guardian->id, 'pricing_rule_id' => $rule->id, 'status' => 'completed', 'started_at' => $now->copy()->subHours(2), 'expected_end_at' => $now->copy()->subHour(), 'ended_at' => $now->copy()->subMinutes(50), 'pricing_snapshot_json' => ['currency' => 'EGP', 'base_price_minor' => 15000], 'created_by_user_id' => $owner->id, 'lock_version' => 2, 'checkout_guardian_id' => $guardian->id, 'checkout_verification_method' => 'phone_last_four', 'checkout_verified_by_user_id' => $owner->id, 'checkout_verified_at' => $now->copy()->subMinutes(51), 'checkout_prepared_at' => $now->copy()->subMinutes(51), 'checkout_snapshot_json' => ['total_minor' => 15000, 'currency' => 'EGP'], 'checkout_amount_due_minor' => 15000, 'ended_by_user_id' => $owner->id],
            );
            PlaySession::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'ticket_id' => $activeTicket->id],
                ['branch_id' => $branch->id, 'child_id' => $child->id, 'guardian_id' => $guardian->id, 'pricing_rule_id' => $rule->id, 'status' => 'active', 'started_at' => $now->copy()->subMinutes(55), 'expected_end_at' => $now->copy()->addMinutes(5), 'pricing_snapshot_json' => ['currency' => 'EGP', 'base_price_minor' => 15000], 'created_by_user_id' => $owner->id, 'lock_version' => 1],
            );
            $order = Order::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'creation_idempotency_key' => 'm6-pilot-paid-order'],
                ['creation_fingerprint' => hash('sha256', 'm6-pilot-paid-order'), 'branch_id' => $branch->id, 'guardian_id' => $guardian->id, 'session_id' => $completed->id, 'receipt_number' => 'PN-ALPHA-2026-900001', 'status' => 'paid', 'subtotal_minor' => 15000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 15000, 'paid_minor' => 15000, 'refunded_minor' => 0, 'currency' => 'EGP', 'receipt_snapshot_json' => ['receipt_number' => 'PN-ALPHA-2026-900001', 'total_minor' => 15000, 'currency' => 'EGP'], 'receipt_issued_at' => $now->copy()->subMinutes(50), 'opened_by_user_id' => $owner->id, 'paid_by_user_id' => $owner->id, 'paid_at' => $now->copy()->subMinutes(50), 'lock_version' => 2],
            );
            Payment::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'idempotency_key' => 'm6-pilot-payment'],
                ['branch_id' => $branch->id, 'order_id' => $order->id, 'method' => 'cash', 'status' => 'posted', 'amount_minor' => 15000, 'currency' => 'EGP', 'posted_by_user_id' => $owner->id, 'posted_at' => $now->copy()->subMinutes(50), 'request_fingerprint' => hash('sha256', 'm6-pilot-payment')],
            );
            DB::table('audit_logs')->insertOrIgnore(['tenant_id' => $tenant->id, 'request_id' => '00000000-0000-4000-8000-000000000006', 'branch_id' => $branch->id, 'actor_user_id' => $owner->id, 'actor_type' => 'user', 'action' => 'session.cash_settled', 'subject_type' => 'play_session', 'subject_id' => (string) $completed->id, 'outcome' => 'success', 'reason_code' => 'cash_payment', 'before_json' => null, 'after_json' => json_encode(['status' => 'completed', 'branch_id' => $branch->id], JSON_THROW_ON_ERROR), 'occurred_at' => $now->copy()->subMinutes(50)]);
        });

        app(CollectOperationalNotifications::class)->run();
    }

    private function ticket(int $tenantId, int $branchId, int $typeId, int $guardianId, int $childId, int $ownerId, string $displayCode, $now): Ticket
    {
        return Ticket::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'display_code' => $displayCode],
            ['branch_id' => $branchId, 'ticket_type_id' => $typeId, 'guardian_id' => $guardianId, 'child_id' => $childId, 'service_date' => $now->copy()->setTimezone('Africa/Cairo')->toDateString(), 'status' => 'issued', 'code_hash' => hash('sha256', $displayCode), 'code_payload_encrypted' => 'demo-'.$displayCode, 'price_minor' => 15000, 'currency' => 'EGP', 'price_snapshot_json' => ['currency' => 'EGP', 'price_minor' => 15000], 'issued_at' => $now, 'valid_from' => $now->copy()->subDay(), 'valid_until' => $now->copy()->addDay(), 'uses_count' => 0, 'max_uses' => 1, 'idempotency_key' => (string) Str::uuid(), 'issue_fingerprint' => hash('sha256', 'issue-'.$displayCode), 'issued_by_user_id' => $ownerId, 'lock_version' => 1],
        );
    }
}
