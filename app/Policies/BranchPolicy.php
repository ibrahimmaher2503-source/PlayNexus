<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\CustomRole;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class BranchPolicy
{
    private const VIEW_ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
        'cashier',
    ];

    public function view(User $user, Branch $branch): bool
    {
        $freshUser = User::query()->whereKey($user->getAuthIdentifier())->first();
        $freshBranch = Branch::query()->whereKey($branch->getKey())->first();

        if (! $freshUser || $freshUser->status !== 'active' || ! $freshBranch || ! $freshBranch->is_active) {
            return false;
        }

        $tenant = app(TenantContext::class)->current($freshUser);

        if (! $tenant || (int) $freshBranch->tenant_id !== (int) $freshUser->tenant_id) {
            return false;
        }

        if (DB::table('tenant_owners')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $freshUser->getKey())
            ->exists()) {
            return true;
        }

        $assignment = $freshUser->branches()
            ->whereKey($freshBranch)
            ->where('branches.tenant_id', $tenant->id)
            ->where('branches.is_active', true)
            ->wherePivot('tenant_id', $tenant->id)
            ->wherePivot('is_active', true)
            ->first();

        if (! $assignment) {
            return false;
        }

        if (in_array($assignment->pivot->role, self::VIEW_ROLES, true)) {
            return true;
        }

        return CustomRole::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $assignment->pivot->role)
            ->whereHas('permissions', fn ($query) => $query->where('permission', 'branches.view'))
            ->exists();
    }

    public function manageStaff(User $user, Branch $branch): Response
    {
        $freshUser = User::query()->whereKey($user->getAuthIdentifier())->first();
        $freshBranch = Branch::query()->whereKey($branch->getKey())->first();

        if (! $freshUser || $freshUser->status !== 'active' || ! $freshBranch || ! $freshBranch->is_active) {
            return Response::denyAsNotFound();
        }

        if (! $freshUser->tenant_id || (int) $freshBranch->tenant_id !== (int) $freshUser->tenant_id) {
            return Response::denyAsNotFound();
        }

        if (DB::table('tenant_owners')
            ->where('tenant_id', $freshBranch->tenant_id)
            ->where('user_id', $freshUser->getKey())
            ->exists()) {
            return Response::allow();
        }

        if (DB::table('branch_user')
            ->where('tenant_id', $freshBranch->tenant_id)
            ->where('branch_id', $freshBranch->getKey())
            ->where('user_id', $freshUser->getKey())
            ->where('role', 'branch_manager')
            ->where('is_active', true)
            ->exists()) {
            return Response::allow();
        }

        return DB::table('branch_user')
            ->join('branches', function ($join) use ($freshBranch): void {
                $join->on('branches.id', '=', 'branch_user.branch_id')
                    ->where('branches.tenant_id', $freshBranch->tenant_id)
                    ->where('branches.is_active', true);
            })
            ->where('branch_user.tenant_id', $freshBranch->tenant_id)
            ->where('branch_user.user_id', $freshUser->getKey())
            ->where('branch_user.role', 'branch_manager')
            ->where('branch_user.is_active', true)
            ->exists()
            ? Response::denyAsNotFound()
            : Response::deny();
    }

    public function updateSettings(User $user, Branch $branch): Response
    {
        return $this->manageConfiguration($user, $branch, 'branches.update');
    }

    public function changeStatus(User $user, Branch $branch): Response
    {
        return $this->manageConfiguration($user, $branch, 'branches.status');
    }

    private function manageConfiguration(User $user, Branch $branch, string $permission): Response
    {
        $freshUser = User::query()->whereKey($user->getAuthIdentifier())->where('status', 'active')->first();
        $freshBranch = Branch::query()->whereKey($branch->getKey())->first();

        if (! $freshUser || ! $freshBranch || ! $freshUser->tenant_id || (int) $freshUser->tenant_id !== (int) $freshBranch->tenant_id) {
            return Response::denyAsNotFound();
        }

        if (! DB::table('tenants')->where('id', $freshBranch->tenant_id)->where('is_active', true)->exists()) {
            return Response::denyAsNotFound();
        }

        if (DB::table('tenant_owners')
            ->where('tenant_id', $freshBranch->tenant_id)
            ->where('user_id', $freshUser->getKey())
            ->exists()) {
            return Response::allow();
        }

        $assignment = DB::table('branch_user')
            ->where('tenant_id', $freshBranch->tenant_id)
            ->where('branch_id', $freshBranch->getKey())
            ->where('user_id', $freshUser->getKey())
            ->where('is_active', true)
            ->first(['role']);

        if (! $assignment) {
            return Response::denyAsNotFound();
        }

        if ($assignment->role === 'branch_manager') {
            return Response::allow();
        }

        $customRoleAllows = CustomRole::query()
            ->where('tenant_id', $freshBranch->tenant_id)
            ->where('code', $assignment->role)
            ->whereHas('permissions', fn ($query) => $query->where('permission', $permission))
            ->exists();

        return $customRoleAllows ? Response::allow() : Response::deny();
    }
}
