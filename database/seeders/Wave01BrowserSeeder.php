<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class Wave01BrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || DB::getDriverName() !== 'sqlite' || basename(DB::connection()->getDatabaseName()) !== 'wave01.sqlite') {
            throw new LogicException('Wave browser fixtures require the disposable wave01.sqlite database.');
        }
        $admin = User::query()->firstOrCreate(['email' => 'wave01.platform@playnexus.test'], [
            'tenant_id' => null, 'name' => '[DEMO] Wave 01 Platform Admin',
            'password' => Hash::make('PlayNexusDemoOnly-2026'),
        ]);
        $admin->forceFill([
            'status' => 'active', 'mfa_secret' => null, 'mfa_confirmed_at' => null,
            'mfa_last_counter' => null, 'mfa_recovery_codes' => null,
            'auth_version' => (int) $admin->auth_version + 1,
        ])->save();
        DB::table('platform_admins')->updateOrInsert(['user_id' => $admin->id], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
