<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Services\TenantContext;
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

        return in_array($assignment->pivot->role, self::VIEW_ROLES, true);
    }
}
