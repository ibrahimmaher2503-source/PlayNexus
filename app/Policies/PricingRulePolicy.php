<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PricingRulePolicy
{
    private const VIEW_ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
        'cashier',
    ];

    public function viewAny(User $user): bool
    {
        [$freshUser, $tenant] = $this->context($user);

        if (! $freshUser || ! $tenant) {
            return false;
        }

        if ($this->isOwner($freshUser, $tenant)) {
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
            ->whereIn('branch_user.role', self::VIEW_ROLES)
            ->exists();
    }

    public function view(User $user, PricingRule $rule): bool
    {
        [$freshUser, $tenant] = $this->context($user);

        $freshRule = PricingRule::query()
            ->whereKey($rule->getKey())
            ->where('status', 'active')
            ->first();

        if (! $freshUser || ! $tenant || ! $freshRule || (int) $freshRule->tenant_id !== (int) $tenant->getKey() || ! $freshRule->branch_id) {
            return false;
        }

        $branch = Branch::query()
            ->whereKey($freshRule->branch_id)
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->first();

        return $branch !== null && $this->viewBranchForTenant($freshUser, $branch, $tenant);
    }

    public function create(User $user, PricingRule|Branch $target): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        $branchId = $target instanceof PricingRule ? $target->branch_id : $target->getKey();
        $branch = Branch::query()->whereKey($branchId)->first();

        return $freshUser !== null
            && $tenant !== null
            && $branch !== null
            && $this->manageBranch($freshUser, $branch, $tenant);
    }

    public function canCreateBranch(User $user, Branch $branch): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        $freshBranch = Branch::query()->whereKey($branch->getKey())->first();

        return $freshUser !== null
            && $tenant !== null
            && $freshBranch !== null
            && $this->manageBranch($freshUser, $freshBranch, $tenant);
    }

    public function viewBranch(User $user, Branch $branch): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        $freshBranch = Branch::query()->whereKey($branch->getKey())->first();

        return $freshUser !== null
            && $tenant !== null
            && $freshBranch !== null
            && $this->viewBranchForTenant($freshUser, $freshBranch, $tenant);
    }

    private function viewBranchForTenant(User $user, Branch $branch, Tenant $tenant): bool
    {
        if ((int) $branch->tenant_id !== (int) $tenant->getKey() || ! $branch->is_active) {
            return false;
        }

        if ($this->isOwner($user, $tenant)) {
            return true;
        }

        return DB::table('branch_user')
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->whereIn('role', self::VIEW_ROLES)
            ->exists();
    }

    private function manageBranch(User $user, Branch $branch, Tenant $tenant): bool
    {
        if (! $this->viewBranchForTenant($user, $branch, $tenant)) {
            return false;
        }

        if ($this->isOwner($user, $tenant)) {
            return true;
        }

        return DB::table('branch_user')
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->where('role', 'branch_manager')
            ->exists();
    }

    /** @return array{User|null, Tenant|null} */
    private function context(User $user): array
    {
        $freshUser = User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('status', 'active')
            ->first();

        if (! $freshUser || ! $freshUser->tenant_id) {
            return [null, null];
        }

        return [
            $freshUser,
            Tenant::query()
                ->whereKey($freshUser->tenant_id)
                ->where('is_active', true)
                ->first(),
        ];
    }

    private function isOwner(User $user, Tenant $tenant): bool
    {
        return DB::table('tenant_owners')
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
