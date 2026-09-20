<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SubscriptionAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TenantSubscriptionController extends Controller
{
    /**
     * Commercial terms are platform-managed.  The tenant owner can inspect
     * the current record and capacity only; there are intentionally no
     * tenant-side mutations in this controller.
     */
    public function show(Request $request): View
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()
            ->with('currentSubscription.plan')
            ->whereKey($actor->tenant_id)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $tenant);

        $subscription = $tenant->currentSubscription;
        $access = SubscriptionAccess::forTenant($tenant);
        $usage = [
            'branches' => Branch::query()->where('tenant_id', $tenant->getKey())->count(),
            'users' => User::query()->where('tenant_id', $tenant->getKey())->count(),
        ];
        $limits = [
            'branches' => $access->limitFor('branches'),
            'users' => $access->limitFor('users'),
        ];

        return view('tenant.subscription', compact('tenant', 'subscription', 'access', 'usage', 'limits'));
    }
}
