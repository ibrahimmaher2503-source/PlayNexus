<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PosPolicy
{
    private const VIEW_ROLES = ['branch_manager', 'reception_staff', 'reception', 'cashier'];

    public function viewAny(User $user): bool
    {
        [$freshUser, $tenant] = $this->context($user);

        return $freshUser !== null && $tenant !== null && (
            $this->isOwner($freshUser, $tenant)
            || DB::table('branch_user')->where('tenant_id', $tenant->getKey())
                ->where('user_id', $freshUser->getKey())->where('is_active', true)
                ->whereIn('role', self::VIEW_ROLES)->exists()
        );
    }

    public function viewBranch(User $user, Branch $branch): bool
    {
        [$freshUser, $tenant] = $this->context($user);

        if (! $freshUser || ! $tenant || (int) $branch->tenant_id !== (int) $tenant->getKey() || ! $branch->is_active) {
            return false;
        }

        return $this->isOwner($freshUser, $tenant) || DB::table('branch_user')
            ->where('tenant_id', $tenant->getKey())->where('branch_id', $branch->getKey())
            ->where('user_id', $freshUser->getKey())->where('is_active', true)
            ->whereIn('role', self::VIEW_ROLES)->exists();
    }

    public function createOrder(User $user, Branch $branch): bool
    {
        return $this->transactionBranch($user, $branch);
    }

    public function payOrder(User $user, Branch $branch): bool
    {
        return $this->transactionBranch($user, $branch);
    }

    public function manage(User $user, Product|Branch $target, ?Branch $context = null): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        if (! $freshUser || ! $tenant) {
            return false;
        }

        if ($target instanceof Product && $target->tenant_id !== null && (int) $target->tenant_id !== (int) $tenant->getKey()) {
            return false;
        }

        $branch = $target instanceof Product && $target->branch_id === null
            ? $context
            : Branch::query()->where('tenant_id', $tenant->getKey())->whereKey($target instanceof Product ? $target->branch_id : $target->getKey())->first();

        if (! $branch || ! $this->viewBranch($freshUser, $branch)) {
            return false;
        }

        if ($target instanceof Product && $target->branch_id === null) {
            return $this->isOwner($freshUser, $tenant);
        }

        return $this->isOwner($freshUser, $tenant) || DB::table('branch_user')
            ->where('tenant_id', $tenant->getKey())->where('branch_id', $branch->getKey())
            ->where('user_id', $freshUser->getKey())->where('is_active', true)
            ->where('role', 'branch_manager')->exists();
    }

    /** @return array{User|null, Tenant|null} */
    private function context(User $user): array
    {
        $freshUser = User::query()->whereKey($user->getAuthIdentifier())->where('status', 'active')->first();

        return [$freshUser, $freshUser ? Tenant::query()->whereKey($freshUser->tenant_id)->where('is_active', true)->first() : null];
    }

    private function isOwner(User $user, Tenant $tenant): bool
    {
        return DB::table('tenant_owners')->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->exists();
    }

    private function transactionBranch(User $user, Branch $branch): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        if (! $freshUser || ! $tenant || (int) $branch->tenant_id !== (int) $tenant->getKey() || ! $branch->is_active) {
            return false;
        }

        return $this->isOwner($freshUser, $tenant) || DB::table('branch_user')->where('tenant_id', $tenant->getKey())->where('branch_id', $branch->getKey())
            ->where('user_id', $freshUser->getKey())->where('is_active', true)->whereIn('role', ['branch_manager', 'cashier'])->exists();
    }
}
