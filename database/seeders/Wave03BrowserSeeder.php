<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\PlaySession;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class Wave03BrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || DB::getDriverName() !== 'sqlite' || basename(DB::connection()->getDatabaseName()) !== 'wave03.sqlite') {
            throw new LogicException('Wave 03 browser fixtures require the disposable wave03.sqlite database.');
        }

        $this->call(DatabaseSeeder::class);
        $tenant = Tenant::query()->where('internal_identifier', 'demo-nile-alpha')->firstOrFail();
        $owner = $tenant->users()->where('email', 'demo.alpha.owner@playnexus.test')->firstOrFail();
        $main = $tenant->branches()->where('code', 'ALPHA-MAIN')->firstOrFail();
        $north = $tenant->branches()->where('code', 'ALPHA-NORTH')->firstOrFail();
        $now = CarbonImmutable::now('UTC');

        User::query()->where('tenant_id', $tenant->id)->update([
            'mfa_secret' => null,
            'mfa_confirmed_at' => null,
            'mfa_last_counter' => null,
            'mfa_recovery_codes' => null,
            'auth_version' => 1,
        ]);
        foreach ([$main, $north] as $branch) {
            $branch->forceFill([
                'capacity' => $branch->is($north) ? 1 : 20,
                'payment_methods' => ['cash'],
                'is_active' => true,
            ])->save();
            foreach (range(1, 7) as $weekday) {
                DB::table('branch_opening_hours')->updateOrInsert(
                    ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'weekday' => $weekday],
                    ['opens_at' => '00:00:00', 'closes_at' => '23:59:59', 'is_closed' => false, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        [$mainRule, $mainType] = $this->pricing($tenant, $main, $owner);
        [$northRule, $northType] = $this->pricing($tenant, $north, $owner);

        [$validGuardian, $validChild] = $this->family($tenant, $owner, '[W3] Check-in Child', '+201011110001');
        $this->ticket($tenant, $main, $mainType, $mainRule, $owner, $validGuardian, $validChild, 'PN-W3-VALID', 'w3_valid_opaque_checkin_token_000000000000000001');

        [$wrongGuardian, $wrongChild] = $this->family($tenant, $owner, '[W3] Wrong Branch Child', '+201011110002');
        $this->ticket($tenant, $north, $northType, $northRule, $owner, $wrongGuardian, $wrongChild, 'PN-W3-WRONG', 'w3_wrong_branch_token_000000000000000000001');

        [$fullGuardian, $fullChild] = $this->family($tenant, $owner, '[W3] Capacity Child', '+201011110003');
        $this->ticket($tenant, $north, $northType, $northRule, $owner, $fullGuardian, $fullChild, 'PN-W3-FULL', 'w3_capacity_token_00000000000000000000001');
        [$fillerGuardian, $fillerChild] = $this->family($tenant, $owner, '[W3] Capacity Occupant', '+201011110004');
        $fillerTicket = $this->ticket($tenant, $north, $northType, $northRule, $owner, $fillerGuardian, $fillerChild, 'PN-W3-OCCUPIED', 'w3_capacity_occupant_000000000000000000001');
        $this->activeSession($tenant, $north, $northRule, $owner, $fillerGuardian, $fillerChild, $fillerTicket, $now->subMinutes(10));

        [$checkoutGuardian, $checkoutChild] = $this->family($tenant, $owner, '[W3] Checkout Child', '+201012345678');
        $checkoutTicket = $this->ticket($tenant, $main, $mainType, $mainRule, $owner, $checkoutGuardian, $checkoutChild, 'PN-W3-CHECKOUT', 'w3_checkout_token_0000000000000000000000001');
        $this->activeSession($tenant, $main, $mainRule, $owner, $checkoutGuardian, $checkoutChild, $checkoutTicket, $now->subSeconds(4201));

        [$overrideGuardian, $overrideChild] = $this->family($tenant, $owner, '[W3] Override Child', '+201011110005');
        $overrideTicket = $this->ticket($tenant, $main, $mainType, $mainRule, $owner, $overrideGuardian, $overrideChild, 'PN-W3-OVERRIDE', 'w3_override_token_0000000000000000000000001');
        $this->activeSession($tenant, $main, $mainRule, $owner, $overrideGuardian, $overrideChild, $overrideTicket, $now->subMinutes(75));
    }

    /** @return array{PricingRule, TicketType} */
    private function pricing(Tenant $tenant, Branch $branch, User $owner): array
    {
        $rule = PricingRule::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'code' => 'W3-'.$branch->code,
            'name' => '[W3] 60 minute package',
            'version' => 1,
            'billing_mode' => 'fixed_duration',
            'base_duration_seconds' => 3600,
            'base_price_minor' => 15000,
            'grace_period_seconds' => 600,
            'overtime_unit_seconds' => 1800,
            'overtime_price_minor' => 7500,
            'currency' => 'EGP',
            'tax_rate_bps' => 1400,
            'tax_mode' => 'exclusive',
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);
        $type = TicketType::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'pricing_rule_id' => $rule->id,
            'code' => 'W3-'.$branch->code,
            'name' => '[W3] Play ticket',
            'price_minor' => 15000,
            'currency' => 'EGP',
            'max_uses' => 1,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        return [$rule, $type];
    }

    /** @return array{Guardian, Child} */
    private function family(Tenant $tenant, User $owner, string $childName, string $phone): array
    {
        $guardian = Guardian::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $childName.' Guardian',
            'phone_e164' => $phone,
            'preferred_locale' => 'en',
            'status' => 'active',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $child = Child::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $childName,
            'date_of_birth' => now()->subYears(7)->toDateString(),
            'emergency_contact_name' => 'Wave 3 emergency contact',
            'emergency_contact_phone_e164' => $phone,
            'safety_notes_encrypted' => 'Operational safety warning for authorized reception only.',
            'status' => 'active',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => 'legal_guardian',
            'can_consent' => true,
            'can_check_out' => true,
            'is_primary' => true,
            'verification_method' => 'registered_phone_last_four',
            'verified_at' => now('UTC'),
            'verified_by_user_id' => $owner->id,
            'is_active' => true,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        DB::table('family_consent_events')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'consent_type' => 'child_data',
            'status' => 'granted',
            'notice_version' => '2026-09-12',
            'purpose_snapshot' => 'Safety and child-data processing',
            'data_categories_snapshot' => 'Child identity and emergency contact',
            'locale' => 'en',
            'method' => 'staff_recorded',
            'actor_user_id' => $owner->id,
            'branch_id' => null,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now('UTC')->subMinute(),
            'created_at' => now('UTC')->subMinute(),
            'updated_at' => now('UTC')->subMinute(),
        ]);

        return [$guardian, $child];
    }

    private function ticket(Tenant $tenant, Branch $branch, TicketType $type, PricingRule $rule, User $owner, Guardian $guardian, Child $child, string $displayCode, string $payload): Ticket
    {
        $now = CarbonImmutable::now('UTC');
        $snapshot = [
            'pricing_rule_id' => $rule->id,
            'branch_timezone' => $branch->timezone,
            'price_minor' => 15000,
            'base_duration_seconds' => 3600,
            'grace_period_seconds' => 600,
            'overtime_unit_seconds' => 1800,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 1400,
            'tax_mode' => 'exclusive',
            'currency' => 'EGP',
        ];

        return Ticket::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'ticket_type_id' => $type->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'service_date' => $now->setTimezone($branch->timezone)->toDateString(),
            'status' => 'issued',
            'code_hash' => hash('sha256', $payload),
            'code_payload_encrypted' => $payload,
            'display_code' => $displayCode,
            'price_minor' => 15000,
            'currency' => 'EGP',
            'price_snapshot_json' => $snapshot,
            'issued_at' => $now,
            'valid_from' => $now->subDay(),
            'valid_until' => $now->addDay(),
            'uses_count' => 0,
            'max_uses' => 1,
            'idempotency_key' => (string) Str::uuid(),
            'issue_fingerprint' => hash('sha256', 'issue-'.$displayCode),
            'issued_by_user_id' => $owner->id,
            'lock_version' => 1,
        ]);
    }

    private function activeSession(Tenant $tenant, Branch $branch, PricingRule $rule, User $owner, Guardian $guardian, Child $child, Ticket $ticket, CarbonImmutable $startedAt): PlaySession
    {
        $ticket->forceFill([
            'status' => 'consumed',
            'consumed_at' => $startedAt,
            'uses_count' => 1,
            'assignment_locked_at' => $startedAt,
            'lock_version' => 2,
        ])->save();

        return PlaySession::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'ticket_id' => $ticket->id,
            'pricing_rule_id' => $rule->id,
            'status' => 'active',
            'started_at' => $startedAt,
            'expected_end_at' => $startedAt->addHour(),
            'pricing_snapshot_json' => $ticket->price_snapshot_json,
            'created_by_user_id' => $owner->id,
            'lock_version' => 1,
        ]);
    }
}
