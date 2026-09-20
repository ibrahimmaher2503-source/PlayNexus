<?php

namespace App\Http\Controllers;

use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $now = now('UTC');
        $statusCounts = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentTenants = Tenant::query()
            ->select(['id', 'name', 'internal_identifier', 'status', 'created_at'])
            ->withCount('branches')
            ->latest('created_at')
            ->latest('id')
            ->limit(5)
            ->get();

        $attentionTenants = Tenant::query()
            ->select(['id', 'name', 'internal_identifier', 'status', 'created_at'])
            ->whereIn('status', ['pending', 'suspended'])
            ->orderByRaw("case status when 'suspended' then 0 else 1 end")
            ->oldest('created_at')
            ->oldest('id')
            ->limit(5)
            ->get();

        $activeSupportGrants = SupportAccessGrant::query()
            ->with('tenant:id,name,internal_identifier')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit(5)
            ->get(['id', 'tenant_id', 'scope', 'expires_at']);

        return view('platform.dashboard', [
            'actor' => $request->user(),
            'tenantTotal' => (int) $statusCounts->sum(),
            'statusCounts' => [
                'active' => (int) ($statusCounts['active'] ?? 0),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'suspended' => (int) ($statusCounts['suspended'] ?? 0),
            ],
            'recentTenants' => $recentTenants,
            'attentionTenants' => $attentionTenants,
            'activeSupportGrants' => $activeSupportGrants,
        ]);
    }
}
