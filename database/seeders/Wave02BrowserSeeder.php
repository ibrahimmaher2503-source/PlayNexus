<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class Wave02BrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || DB::getDriverName() !== 'sqlite' || basename(DB::connection()->getDatabaseName()) !== 'wave02.sqlite') {
            throw new LogicException('Wave 02 browser fixtures require the disposable wave02.sqlite database.');
        }

        $this->call(M6PilotSeeder::class);

        DB::table('users')->whereIn('email', [
            'demo.alpha.owner@playnexus.test',
            'demo.alpha.branch_manager@playnexus.test',
            'demo.alpha.reception_staff@playnexus.test',
            'demo.alpha.cashier@playnexus.test',
            'demo.beta.owner@playnexus.test',
        ])->update([
            'mfa_secret' => null,
            'mfa_confirmed_at' => null,
            'mfa_last_counter' => null,
            'mfa_recovery_codes' => null,
            'auth_version' => 1,
        ]);

        $alpha = Tenant::query()->where('internal_identifier', 'demo-nile-alpha')->firstOrFail();
        $alphaOwner = $alpha->users()->where('email', 'demo.alpha.owner@playnexus.test')->firstOrFail();
        foreach ($alpha->branches()->get() as $branch) {
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                DB::table('branch_opening_hours')->updateOrInsert(
                    ['tenant_id' => $alpha->id, 'branch_id' => $branch->id, 'weekday' => $weekday],
                    ['opens_at' => '00:00:00', 'closes_at' => '23:59:59', 'is_closed' => false, 'created_at' => now('UTC'), 'updated_at' => now('UTC')],
                );
            }
        }
        $eligible = Guardian::query()->updateOrCreate(
            ['tenant_id' => $alpha->id, 'phone_e164' => '+201055500001'],
            [
                'full_name' => '[DEMO] Retention Eligible Guardian',
                'preferred_locale' => 'ar',
                'status' => 'inactive',
                'created_by_user_id' => $alphaOwner->id,
                'updated_by_user_id' => $alphaOwner->id,
            ],
        );
        DB::table('guardians')->where('id', $eligible->id)->update([
            'created_at' => now('UTC')->subYears(4),
            'updated_at' => now('UTC')->subYears(4),
        ]);

        $beta = Tenant::query()->where('internal_identifier', 'demo-nile-beta')->firstOrFail();
        $betaOwner = $beta->users()->where('email', 'demo.beta.owner@playnexus.test')->firstOrFail();
        Guardian::query()->updateOrCreate(
            ['tenant_id' => $beta->id, 'phone_e164' => '+201055500002'],
            [
                'full_name' => '[DEMO] Foreign Guardian',
                'preferred_locale' => 'en',
                'status' => 'active',
                'created_by_user_id' => $betaOwner->id,
                'updated_by_user_id' => $betaOwner->id,
            ],
        );
    }
}
