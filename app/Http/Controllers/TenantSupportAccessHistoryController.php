<?php

namespace App\Http\Controllers;

use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantSupportAccessHistoryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $actor */
        $actor = $request->user();
        $tenant = app(TenantContext::class)->current($actor);
        abort_unless($tenant && DB::table('tenant_owners')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)->exists(), 403);

        return view('tenant.support-access.index', [
            'tenant' => $tenant,
            // Tenant Owners can see the exceptional access record, but not
            // platform administrator identities or unrelated audit entries.
            'grants' => SupportAccessGrant::query()->where('tenant_id', $tenant->id)
                ->select(['id', 'scope', 'reason', 'support_ticket', 'granted_at', 'expires_at', 'revoked_at', 'revocation_reason'])
                ->orderByDesc('granted_at')->orderByDesc('id')->paginate(25),
        ]);
    }
}
