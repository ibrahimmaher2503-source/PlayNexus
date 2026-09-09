<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

class TenantContext
{
    public function current(?Authenticatable $user = null): ?Tenant
    {
        $user ??= auth()->user();

        if (! $user || ! $user->tenant_id) {
            return null;
        }

        return Tenant::query()->whereKey($user->tenant_id)->where('is_active', true)->first();
    }
}
