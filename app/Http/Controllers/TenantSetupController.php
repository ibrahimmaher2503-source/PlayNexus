<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BranchReadiness;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TenantSetupController extends Controller
{
    /**
     * This is deliberately a read-only guide. Its state is derived from the
     * current tenant records, rather than storing an unverified completion flag.
     */
    public function index(Request $request): View
    {
        $actor = User::query()->whereKey($request->user()->getAuthIdentifier())->where('status', 'active')->firstOrFail();
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('view', $tenant);

        $branches = Branch::query()->where('tenant_id', $tenant->id)->get([
            'id', 'code', 'name', 'timezone', 'capacity', 'currency', 'tax_rate_bps', 'tax_mode', 'receipt_prefix', 'payment_methods', 'is_active',
        ]);
        $hoursByBranch = DB::table('branch_opening_hours')->where('tenant_id', $tenant->id)
            ->get(['branch_id', 'weekday', 'opens_at', 'closes_at', 'is_closed'])->groupBy('branch_id');
        $readyBranches = $branches->filter(fn (Branch $branch): bool => BranchReadiness::isActivationReady($branch, $hoursByBranch->get($branch->id, collect())));

        $profileReady = filled($tenant->name)
            && filled($tenant->legal_name)
            && in_array($tenant->default_locale, ['en', 'ar'], true)
            && in_array($tenant->timezone, DateTimeZone::listIdentifiers(), true)
            && $tenant->currency === 'EGP';
        $activeBranches = $branches->where('is_active', true)->count();
        $activeStaff = User::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count();
        $assignedStaff = DB::table('branch_user')
            ->join('users', function ($join) use ($tenant): void {
                $join->on('users.id', '=', 'branch_user.user_id')
                    ->where('users.tenant_id', $tenant->id)
                    ->where('users.status', 'active');
            })
            ->join('branches', function ($join) use ($tenant): void {
                $join->on('branches.id', '=', 'branch_user.branch_id')
                    ->where('branches.tenant_id', $tenant->id)
                    ->where('branches.is_active', true);
            })
            ->where('branch_user.tenant_id', $tenant->id)->where('branch_user.is_active', true)
            ->distinct('branch_user.user_id')->count('branch_user.user_id');
        $activePricing = DB::table('pricing_rules')->where('tenant_id', $tenant->id)->where('status', 'active')->count();
        $activeProducts = DB::table('products')->where('tenant_id', $tenant->id)->where('status', 'active')->whereNull('archived_at')->count();

        $steps = [
            ['key' => 'profile', 'complete' => $profileReady, 'optional' => false, 'route' => 'tenant.settings.edit'],
            ['key' => 'branches', 'complete' => $activeBranches > 0, 'optional' => false, 'route' => 'branches.manage', 'count' => $branches->count(), 'ready_count' => $readyBranches->count()],
            ['key' => 'staff', 'complete' => $activeStaff > 1 && $assignedStaff > 0, 'optional' => true, 'route' => 'staff.index', 'count' => $activeStaff, 'assigned_count' => $assignedStaff],
            ['key' => 'pricing', 'complete' => $activePricing > 0, 'optional' => true, 'route' => 'pricing.index', 'count' => $activePricing],
            ['key' => 'catalog', 'complete' => $activeProducts > 0, 'optional' => true, 'route' => 'pos.index', 'count' => $activeProducts],
        ];

        return view('tenant.setup.index', [
            'tenant' => $tenant,
            'steps' => $steps,
            'requiredComplete' => collect($steps)->where('optional', false)->where('complete', true)->count(),
            'requiredTotal' => collect($steps)->where('optional', false)->count(),
        ]);
    }
}
