<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefundPolicy
{
    public function request(User $user, int $tenantId, int $branchId): bool
    {
        return $this->owner($user, $tenantId)
            || $this->hasRole($user, $tenantId, $branchId, 'branch_manager')
            || $this->hasRole($user, $tenantId, $branchId, 'cashier');
    }

    public function approve(User $user, Refund $refund): bool
    {
        if ((int) $refund->requested_by_user_id === (int) $user->getKey()) {
            return false;
        }

        return $this->owner($user, (int) $refund->tenant_id)
            || $this->hasRole($user, (int) $refund->tenant_id, (int) $refund->branch_id, 'branch_manager');
    }

    public function execute(User $user, Refund $refund): bool
    {
        return $this->owner($user, (int) $refund->tenant_id)
            || $this->hasRole($user, (int) $refund->tenant_id, (int) $refund->branch_id, 'branch_manager')
            || $this->hasRole($user, (int) $refund->tenant_id, (int) $refund->branch_id, 'cashier');
    }

    private function hasRole(User $user, int $tenantId, int $branchId, string $role): bool
    {
        $fresh = User::query()->whereKey($user->getAuthIdentifier())->where('tenant_id', $tenantId)->where('status', 'active')->exists();

        return $fresh && DB::table('branch_user')->where('tenant_id', $tenantId)->where('branch_id', $branchId)
            ->where('user_id', $user->getAuthIdentifier())->where('is_active', true)->where('role', $role)->exists();
    }

    private function owner(User $user, int $tenantId): bool
    {
        return User::query()->whereKey($user->getAuthIdentifier())->where('tenant_id', $tenantId)->where('status', 'active')->exists()
            && DB::table('tenant_owners')->where('tenant_id', $tenantId)->where('user_id', $user->getAuthIdentifier())->exists();
    }
}
