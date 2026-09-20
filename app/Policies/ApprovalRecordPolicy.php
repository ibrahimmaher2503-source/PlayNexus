<?php

namespace App\Policies;

use App\Models\ApprovalRecord;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalRecordPolicy
{
    public function request(User $user, Order $order): bool
    {
        $context = $this->context($user);
        $branch = $context[1] && (int) $order->tenant_id === (int) $context[1]->id
            ? Branch::query()->whereKey($order->branch_id)->where('tenant_id', $context[1]->id)->where('is_active', true)->first()
            : null;

        return $context[0] !== null && $context[1] !== null && $branch !== null
            && (int) $order->tenant_id === (int) $context[1]->id
            && $this->branchRole($context[0], $context[1], $branch, ['cashier']);
    }

    public function approve(User $user, ApprovalRecord $approval): bool
    {
        return $this->review($user, $approval);
    }

    public function reject(User $user, ApprovalRecord $approval): bool
    {
        return $this->review($user, $approval);
    }

    public function consume(User $user, ApprovalRecord $approval): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        $branch = $tenant && (int) $approval->tenant_id === (int) $tenant->id
            ? Branch::query()->whereKey($approval->branch_id)->where('tenant_id', $tenant->id)->where('is_active', true)->first()
            : null;

        return $freshUser !== null && $tenant !== null && $branch !== null
            && $this->branchRole($freshUser, $tenant, $branch, ['cashier']);
    }

    private function review(User $user, ApprovalRecord $approval): bool
    {
        [$freshUser, $tenant] = $this->context($user);
        $branch = $tenant && (int) $approval->tenant_id === (int) $tenant->id
            ? Branch::query()->whereKey($approval->branch_id)->where('tenant_id', $tenant->id)->where('is_active', true)->first()
            : null;

        if (! $freshUser || ! $tenant || ! $branch || (int) $approval->requested_by_user_id === (int) $freshUser->id) {
            return false;
        }

        return DB::table('tenant_owners')->where('tenant_id', $tenant->id)->where('user_id', $freshUser->id)->exists()
            || $this->branchRole($freshUser, $tenant, $branch, ['branch_manager']);
    }

    /** @return array{?User, ?Tenant} */
    private function context(User $user): array
    {
        $freshUser = User::query()->whereKey($user->getAuthIdentifier())->where('status', 'active')->first();

        return [$freshUser, $freshUser && $freshUser->tenant_id
            ? Tenant::query()->whereKey($freshUser->tenant_id)->where('is_active', true)->first()
            : null];
    }

    private function branchRole(User $user, Tenant $tenant, Branch $branch, array $roles): bool
    {
        if ((int) $branch->tenant_id !== (int) $tenant->id || ! $branch->is_active) {
            return false;
        }

        return DB::table('branch_user')->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)
            ->where('user_id', $user->id)->where('is_active', true)->whereIn('role', $roles)->exists();
    }
}
