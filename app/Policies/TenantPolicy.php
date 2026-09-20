<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): Response
    {
        $freshUser = User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('status', 'active')
            ->first();
        $freshTenant = Tenant::query()
            ->whereKey($tenant->getKey())
            ->where('is_active', true)
            ->first();

        if (! $freshUser || ! $freshTenant || ! $freshUser->tenant_id || (int) $freshUser->tenant_id !== (int) $freshTenant->id) {
            return Response::denyAsNotFound();
        }

        if (! DB::table('tenant_owners')
            ->where('tenant_id', $freshTenant->id)
            ->where('user_id', $freshUser->getKey())
            ->exists()) {
            return Response::deny();
        }

        return Response::allow();
    }

    public function manageStaff(User $user, Tenant $tenant): Response
    {
        $freshUser = User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('status', 'active')
            ->first();
        $freshTenant = Tenant::query()
            ->whereKey($tenant->getKey())
            ->where('is_active', true)
            ->first();

        if (! $freshUser || ! $freshTenant || ! $freshUser->tenant_id || (int) $freshUser->tenant_id !== (int) $freshTenant->id) {
            return Response::denyAsNotFound();
        }

        if (DB::table('tenant_owners')
            ->where('tenant_id', $freshTenant->id)
            ->where('user_id', $freshUser->getKey())
            ->exists()) {
            return Response::allow();
        }

        if (DB::table('branch_user')
            ->join('branches', function ($join) use ($freshTenant): void {
                $join->on('branches.id', '=', 'branch_user.branch_id')
                    ->where('branches.tenant_id', $freshTenant->id)
                    ->where('branches.is_active', true);
            })
            ->where('branch_user.tenant_id', $freshTenant->id)
            ->where('branch_user.user_id', $freshUser->getKey())
            ->where('branch_user.role', 'branch_manager')
            ->where('branch_user.is_active', true)
            ->exists()) {
            return Response::allow();
        }

        return Response::deny();
    }
}
