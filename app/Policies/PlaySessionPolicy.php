<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\PlaySession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlaySessionPolicy
{
    private const VIEW_ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
        'cashier',
    ];

    private const CHECK_IN_ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
    ];

    private const CHECKOUT_ROLES = [
        'branch_manager',
        'reception_staff',
        'reception',
    ];

    public function viewAny(User $user): bool
    {
        [$freshUser, $tenant] = $this->context($user);

        if (! $freshUser || ! $tenant) {
            return false;
        }

        return $this->isOwner($freshUser, $tenant)
            || DB::table('branch_user')
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

    public function view(User $user, PlaySession $session): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        $freshSession = PlaySession::query()
            ->whereKey($session->getKey())
            ->where('tenant_id', $tenant?->getKey())
            ->first();
        $branch = $freshSession
            ? Branch::query()
                ->whereKey($freshSession->branch_id)
                ->where('tenant_id', $freshSession->tenant_id)
                ->where('is_active', true)
                ->first()
            : null;

        return $freshUser !== null
            && $tenant !== null
            && $branch !== null
            && $this->canViewBranch($freshUser, $branch, $tenant);
    }

    public function viewBranch(User $user, Branch $branch): bool
    {
        [$freshUser, $tenant] = $this->context($user);

        return $freshUser !== null
            && $tenant !== null
            && (int) $branch->tenant_id === (int) $tenant->getKey()
            && $branch->is_active
            && $this->canViewBranch($freshUser, $branch, $tenant);
    }

    public function checkIn(User $user, Branch $branch): bool
    {
        [$freshUser, $tenant] = $this->context($user);

        if (! $freshUser || ! $tenant || (int) $branch->tenant_id !== (int) $tenant->getKey() || ! $branch->is_active) {
            return false;
        }

        if ($this->isOwner($freshUser, $tenant)) {
            return true;
        }

        return DB::table('branch_user')
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('user_id', $freshUser->getKey())
            ->where('is_active', true)
            ->whereIn('role', self::CHECK_IN_ROLES)
            ->exists();
    }

    public function checkout(User $user, PlaySession $session): bool
    {
        return $this->canActOnSession($user, $session, self::CHECKOUT_ROLES);
    }

    public function extend(User $user, PlaySession $session): bool
    {
        return $this->canActOnSession($user, $session, self::CHECK_IN_ROLES);
    }

    public function cancel(User $user, PlaySession $session): bool
    {
        return $this->canActOnSession($user, $session, ['branch_manager'], ownerAllowed: true);
    }

    public function overrideCheckout(User $user, PlaySession $session): bool
    {
        return $this->canActOnSession($user, $session, ['branch_manager'], ownerAllowed: true);
    }

    /** @return array{?User, ?Tenant} */
    private function context(User $user): array
    {
        $freshUser = User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('status', 'active')
            ->first();
        $tenant = $freshUser
            ? Tenant::query()->whereKey($freshUser->tenant_id)->where('is_active', true)->first()
            : null;

        return [$freshUser, $tenant];
    }

    private function isOwner(User $user, Tenant $tenant): bool
    {
        return DB::table('tenant_owners')
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->exists();
    }

    private function canViewBranch(User $user, Branch $branch, Tenant $tenant): bool
    {
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

    /** @param array<int, string> $roles */
    private function canActOnSession(User $user, PlaySession $session, array $roles, bool $ownerAllowed = true): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        $freshSession = $tenant ? PlaySession::query()->whereKey($session->getKey())->where('tenant_id', $tenant->getKey())->first() : null;
        $branch = $freshSession
            ? Branch::query()
                ->whereKey($freshSession->branch_id)
                ->where('tenant_id', $tenant->getKey())
                ->where('is_active', true)
                ->first()
            : null;
        if (! $freshUser || ! $tenant || ! $freshSession || ! $branch) {
            return false;
        }
        if ($ownerAllowed && $this->isOwner($freshUser, $tenant)) {
            return true;
        }

        return DB::table('branch_user')
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $freshSession->branch_id)
            ->where('user_id', $freshUser->getKey())
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->exists();
    }
}
