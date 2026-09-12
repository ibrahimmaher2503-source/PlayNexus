<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuardianPolicy
{
    private const ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
        'cashier',
    ];

    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canAccess($user);
    }

    private function canAccess(User $user): bool
    {
        $freshUser = User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('status', 'active')
            ->first();

        if (! $freshUser || ! $freshUser->tenant_id) {
            return false;
        }

        $tenant = Tenant::query()
            ->whereKey($freshUser->tenant_id)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            return false;
        }

        if (DB::table('tenant_owners')
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $freshUser->getKey())
            ->exists()) {
            return true;
        }

        return DB::table('branch_user')
            ->join('branches', function ($join): void {
                $join->on('branches.id', '=', 'branch_user.branch_id')
                    ->on('branches.tenant_id', '=', 'branch_user.tenant_id');
            })
            ->where('branch_user.tenant_id', $tenant->getKey())
            ->where('branch_user.user_id', $freshUser->getKey())
            ->where('branch_user.is_active', true)
            ->where('branches.is_active', true)
            ->whereIn('branch_user.role', self::ROLES)
            ->exists();
    }
}
