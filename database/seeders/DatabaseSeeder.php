<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Local-only fixture credential; never print or reuse outside demo environments. */
    private const DEMO_PASSWORD = 'PlayNexusDemoOnly-2026';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('The synthetic demo seed is limited to local and testing environments.');
        }

        $password = Hash::make(self::DEMO_PASSWORD);

        DB::transaction(function () use ($password): void {
            foreach ([
                [
                    'key' => 'alpha',
                    'name' => '[DEMO] Nile Play Center Alpha',
                    'internal_identifier' => 'demo-nile-alpha',
                    'owner' => ['name' => '[DEMO] Alpha Owner', 'email' => 'demo.alpha.owner@playnexus.test'],
                    'branches' => [
                        ['code' => 'ALPHA-MAIN', 'name' => '[DEMO] Alpha Main Branch'],
                        ['code' => 'ALPHA-NORTH', 'name' => '[DEMO] Alpha North Branch'],
                    ],
                ],
                [
                    'key' => 'beta',
                    'name' => '[DEMO] Nile Play Center Beta',
                    'internal_identifier' => 'demo-nile-beta',
                    'owner' => ['name' => '[DEMO] Beta Owner', 'email' => 'demo.beta.owner@playnexus.test'],
                    'branches' => [
                        ['code' => 'BETA-MAIN', 'name' => '[DEMO] Beta Main Branch'],
                        ['code' => 'BETA-EAST', 'name' => '[DEMO] Beta East Branch'],
                    ],
                ],
            ] as $tenantData) {
                $tenant = Tenant::query()->updateOrCreate(
                    ['internal_identifier' => $tenantData['internal_identifier']],
                    [
                        'name' => $tenantData['name'],
                        'legal_name' => $tenantData['name'],
                        'default_locale' => 'en',
                        'timezone' => 'Africa/Cairo',
                        'currency' => 'EGP',
                        'is_active' => true,
                        'status' => 'active',
                    ],
                );

                $branches = [];
                foreach ($tenantData['branches'] as $branchData) {
                    $branches[] = Branch::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'code' => $branchData['code']],
                        [
                            'name' => $branchData['name'],
                            'timezone' => 'Africa/Cairo',
                            'capacity' => 40,
                            'currency' => 'EGP',
                            'tax_rate_bps' => 0,
                            'tax_mode' => 'exclusive',
                            'receipt_prefix' => 'PN-'.$tenantData['key'],
                            'payment_methods' => ['cash'],
                            'is_active' => true,
                        ],
                    );
                }

                $owner = User::query()->updateOrCreate(
                    ['email' => $tenantData['owner']['email']],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $tenantData['owner']['name'],
                        'password' => $password,
                        'email_verified_at' => now('UTC'),
                        'status' => 'active',
                    ],
                );

                DB::table('tenant_owners')->updateOrInsert(
                    ['tenant_id' => $tenant->id, 'user_id' => $owner->id],
                    ['created_at' => now('UTC'), 'updated_at' => now('UTC')],
                );

                foreach ([
                    ['role' => 'branch_manager', 'label' => 'Manager'],
                    ['role' => 'reception_staff', 'label' => 'Reception'],
                    ['role' => 'cashier', 'label' => 'Cashier'],
                ] as $staffData) {
                    $staff = User::query()->updateOrCreate(
                        ['email' => 'demo.'.$tenantData['key'].'.'.$staffData['role'].'@playnexus.test'],
                        [
                            'tenant_id' => $tenant->id,
                            'name' => '[DEMO] '.$tenantData['key'].' '.$staffData['label'],
                            'password' => $password,
                            'email_verified_at' => now('UTC'),
                            'status' => 'active',
                        ],
                    );

                    foreach ($branches as $branch) {
                        if ($staffData['role'] !== 'branch_manager' && $branch->code !== $branches[0]->code) {
                            continue;
                        }

                        DB::table('branch_user')->updateOrInsert(
                            [
                                'tenant_id' => $tenant->id,
                                'branch_id' => $branch->id,
                                'user_id' => $staff->id,
                            ],
                            [
                                'role' => $staffData['role'],
                                'is_active' => true,
                                'created_at' => now('UTC'),
                                'updated_at' => now('UTC'),
                            ],
                        );
                    }
                }
            }
        });

        $this->call(SubscriptionDemoSeeder::class);
    }
}
