<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class M0DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_refuses_to_run_outside_local_and_testing(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('limited to local and testing environments');
            app(DatabaseSeeder::class)->run();
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    public function test_demo_seed_is_repeatable_and_keeps_roles_inside_their_tenants(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->snapshot();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $this->snapshot());
        $this->assertSame([
            'tenants' => 2,
            'branches' => 4,
            'users' => 8,
            'owners' => 2,
            'assignments' => 8,
        ], [
            'tenants' => DB::table('tenants')->count(),
            'branches' => DB::table('branches')->count(),
            'users' => DB::table('users')->count(),
            'owners' => DB::table('tenant_owners')->count(),
            'assignments' => DB::table('branch_user')->count(),
        ]);

        $roleCounts = DB::table('branch_user')
            ->select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->orderBy('role')
            ->pluck('total', 'role')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $this->assertSame([
            'branch_manager' => 4,
            'cashier' => 2,
            'reception_staff' => 2,
        ], $roleCounts);

        foreach (DB::table('branch_user')
            ->join('users', 'users.id', '=', 'branch_user.user_id')
            ->join('branches', 'branches.id', '=', 'branch_user.branch_id')
            ->get(['branch_user.tenant_id', 'users.tenant_id as user_tenant_id', 'branches.tenant_id as branch_tenant_id', 'branch_user.is_active']) as $assignment) {
            $this->assertSame((int) $assignment->tenant_id, (int) $assignment->user_tenant_id);
            $this->assertSame((int) $assignment->tenant_id, (int) $assignment->branch_tenant_id);
            $this->assertTrue((bool) $assignment->is_active);
        }

        $this->assertDatabaseCount('guardians', 0);
        $this->assertDatabaseCount('children', 0);
        $this->assertDatabaseCount('ticket_types', 0);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('play_sessions', 0);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'tenants' => DB::table('tenants')->orderBy('internal_identifier')->pluck('id', 'internal_identifier')->all(),
            'branches' => DB::table('branches')->orderBy('tenant_id')->orderBy('code')->get(['id', 'tenant_id', 'code'])->map(fn ($branch): array => (array) $branch)->all(),
            'users' => DB::table('users')->where('email', 'like', 'demo.%@playnexus.test')->orderBy('email')->pluck('id', 'email')->all(),
            'owners' => DB::table('tenant_owners')->orderBy('tenant_id')->orderBy('user_id')->get(['tenant_id', 'user_id'])->map(fn ($owner): array => (array) $owner)->all(),
            'assignments' => DB::table('branch_user')->orderBy('tenant_id')->orderBy('branch_id')->orderBy('user_id')->get(['tenant_id', 'branch_id', 'user_id', 'role', 'is_active'])->map(fn ($assignment): array => (array) $assignment)->all(),
        ];
    }
}
