<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Services\TenantContext;

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
        if (! User::query()->whereKey($user)->where('status', 'active')->exists()) {
            return false;
        }

        if (! app(TenantContext::class)->current($user)) {
            return false;
        }

        if ((int) $branch->tenant_id !== (int) $user->tenant_id || ! $branch->is_active) {
            return false;
        }

        $assignment = $user->branches()
            ->whereKey($branch)
            ->where('branches.tenant_id', $user->tenant_id)
            ->where('branches.is_active', true)
            ->wherePivot('tenant_id', $user->tenant_id)
            ->wherePivot('is_active', true)
            ->first();

        return $assignment !== null
            && in_array($assignment->pivot->role, self::VIEW_ROLES, true);
    }
}
