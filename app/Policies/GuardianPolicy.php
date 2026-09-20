<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuardianPolicy
{
    private const VIEW_ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
        'cashier',
    ];

    private const MANAGE_ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
    ];

    public function viewAny(User $user): bool
    {
        return $this->hasEligibleRole($user, self::VIEW_ROLES);
    }

    public function create(User $user): bool
    {
        return $this->hasEligibleRole($user, self::VIEW_ROLES);
    }

    public function manageSensitiveRegistration(User $user): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES);
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::VIEW_ROLES, $guardian);
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES, $guardian);
    }

    public function createChild(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES, $guardian);
    }

    public function updateChild(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES, $guardian);
    }

    public function manageRelationships(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES, $guardian);
    }

    public function manageConsent(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES, $guardian);
    }

    public function manageSafetyData(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES, $guardian);
    }

    public function viewSensitiveData(User $user, Guardian $guardian): bool
    {
        return $this->hasEligibleRole($user, self::MANAGE_ROLES, $guardian);
    }

    /** @param list<string> $roles */
    private function hasEligibleRole(User $user, array $roles, ?Guardian $guardian = null): bool
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

        if ($guardian !== null && ((int) $guardian->tenant_id !== (int) $tenant->getKey() || $guardian->status !== 'active')) {
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
            ->whereIn('branch_user.role', $roles)
            ->exists();
    }
}
