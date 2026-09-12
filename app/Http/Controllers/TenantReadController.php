<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TenantReadController extends Controller
{
    public function show(Request $request): View
    {
        $user = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($user->tenant_id)->firstOrFail();

        Gate::forUser($user)->authorize('view', $tenant);

        $staff = User::query()
            ->where('tenant_id', $tenant->id)
            ->select(['id', 'name', 'email', 'status'])
            ->orderBy('id')
            ->paginate(25);

        return view('tenant.show', compact('tenant', 'staff'));
    }
}
