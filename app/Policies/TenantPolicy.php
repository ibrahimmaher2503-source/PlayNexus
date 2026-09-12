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
}
