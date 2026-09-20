<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSupportAccessRequest;
use App\Http\Requests\RevokeSupportAccessRequest;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformSupportAccessController extends Controller
{
    public function index(Request $request): View
    {
        return view('platform.support-access.index', [
            'actor' => $request->user(),
            'tenants' => Tenant::query()
                ->select(['id', 'name', 'internal_identifier', 'status'])
                ->orderBy('name')->orderBy('id')->get(),
            'grants' => SupportAccessGrant::query()
                ->with(['tenant:id,name,internal_identifier', 'requestedBy:id'])
                ->orderByDesc('granted_at')->orderByDesc('id')->paginate(25)
                ->withQueryString(),
            'scopes' => config('platform.support_access.scopes', []),
            'maxDurationMinutes' => (int) config('platform.support_access.max_duration_minutes', 60),
        ]);
    }

    public function store(CreateSupportAccessRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = (int) $request->user()->getAuthIdentifier();
        $now = now('UTC');
        $reauthenticatedActor = $this->platformAdmin($actorId);
        $this->auditFailedReauthentication($request, $reauthenticatedActor, (int) $data['target_tenant_id'], $data['current_password']);

        $grant = DB::transaction(function () use ($actorId, $data, $now, $request): SupportAccessGrant {
            $actor = $this->lockedPlatformAdmin($actorId);
            $this->validateCurrentPassword($actor, $data['current_password']);
            $tenant = Tenant::query()->whereKey($data['target_tenant_id'])->lockForUpdate()->firstOrFail();
            $expiresAt = $now->copy()->addMinutes((int) $data['duration_minutes']);

            $grant = SupportAccessGrant::query()->create([
                'tenant_id' => $tenant->getKey(),
                'requested_by_user_id' => $actor->getKey(),
                'scope' => $data['scope'],
                'reason' => $data['reason'],
                'support_ticket' => $data['support_ticket'] ?? null,
                'granted_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            $this->audit($request, $actor, $tenant->getKey(), 'support_access.granted', $grant, 'success', 'support_access_granted', null, [
                'scope' => $grant->scope,
                'expires_at' => $expiresAt->toIso8601String(),
                'support_ticket' => $grant->support_ticket,
            ]);

            return $grant;
        });

        return to_route('platform.support-access.show', $grant)
            ->with('success', __('platform.support_access.granted'));
    }

    public function show(Request $request, SupportAccessGrant $supportAccessGrant): View
    {
        $grant = SupportAccessGrant::query()->findOrFail($supportAccessGrant->getKey());
        $actor = $this->platformAdmin((int) $request->user()->getAuthIdentifier());

        if (! $grant->isActive()) {
            $this->audit($request, $actor, $grant->tenant_id, 'support_access.denied', $grant, 'failure', $grant->revoked_at ? 'support_access_revoked' : 'support_access_expired');
            abort(403, __('platform.support_access.unavailable'));
        }

        $tenant = Tenant::query()->whereKey($grant->tenant_id)->firstOrFail();
        $branchConfigurations = collect();
        $summary = [];

        // Each support scope has a separate, explicitly constrained query.
        // No query joins families, guardians, children, sessions, tickets,
        // orders, payments, safety notes, or ordinary tenant audit records.
        if ($grant->scope === 'tenant_administration_read') {
            $summary = [
                'branches_count' => $tenant->branches()->count(),
                'users_count' => $tenant->users()->count(),
            ];
        } elseif ($grant->scope === 'branch_configuration_read') {
            $branchConfigurations = $tenant->branches()->get([
                'id', 'name', 'code', 'is_active', 'timezone', 'currency', 'capacity',
            ]);
        } else {
            // A stale/removed configuration is denied instead of silently
            // expanding the information that an old grant can disclose.
            $this->audit($request, $actor, $grant->tenant_id, 'support_access.denied', $grant, 'failure', 'support_access_scope_unavailable');
            abort(403, __('platform.support_access.unavailable'));
        }

        $this->audit($request, $actor, $tenant->id, 'support_access.viewed', $grant, 'success', 'support_access_viewed', null, [
            'scope' => $grant->scope,
            'surface' => $grant->scope,
        ]);

        // This surface receives neither tenant authentication context nor
        // operational route links, so a grant cannot become impersonation.
        return view('platform.support-access.show', compact('grant', 'tenant', 'summary', 'branchConfigurations'));
    }

    public function revoke(RevokeSupportAccessRequest $request, SupportAccessGrant $supportAccessGrant): RedirectResponse
    {
        $data = $request->validated();
        $actorId = (int) $request->user()->getAuthIdentifier();
        $reauthenticatedActor = $this->platformAdmin($actorId);
        $this->auditFailedReauthentication($request, $reauthenticatedActor, $supportAccessGrant->tenant_id, $data['current_password'], $supportAccessGrant->getKey());

        DB::transaction(function () use ($actorId, $data, $supportAccessGrant, $request): void {
            $actor = $this->lockedPlatformAdmin($actorId);
            $this->validateCurrentPassword($actor, $data['current_password']);
            $grant = SupportAccessGrant::query()->whereKey($supportAccessGrant->getKey())->lockForUpdate()->firstOrFail();

            if ($grant->revoked_at !== null) {
                abort(409, __('platform.support_access.already_revoked'));
            }

            $now = now('UTC');
            $grant->forceFill([
                'revoked_at' => $now,
                'revoked_by_user_id' => $actor->getKey(),
                'revocation_reason' => $data['reason'],
            ])->save();

            $this->audit($request, $actor, $grant->tenant_id, 'support_access.revoked', $grant, 'success', 'support_access_revoked', [
                'scope' => $grant->scope,
                'expires_at' => $grant->expires_at->toIso8601String(),
            ], [
                'scope' => $grant->scope,
                'expires_at' => $grant->expires_at->toIso8601String(),
                'revoked_at' => $now->toIso8601String(),
            ]);
        });

        return to_route('platform.support-access.index')->with('success', __('platform.support_access.revoked'));
    }

    private function lockedPlatformAdmin(int $actorId): User
    {
        $actor = User::query()
            ->whereKey($actorId)->whereNull('tenant_id')->where('status', 'active')
            ->lockForUpdate()->firstOrFail();

        abort_unless($actor->platformAdmin()->where('is_active', true)->exists(), 403);

        return $actor;
    }

    private function platformAdmin(int $actorId): User
    {
        $actor = User::query()
            ->whereKey($actorId)->whereNull('tenant_id')->where('status', 'active')
            ->firstOrFail();

        abort_unless($actor->platformAdmin()->where('is_active', true)->exists(), 403);

        return $actor;
    }

    private function validateCurrentPassword(User $actor, string $password): void
    {
        if (! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('platform.support_access.current_password_invalid'),
            ]);
        }
    }

    private function auditFailedReauthentication(Request $request, User $actor, int $tenantId, string $password, ?int $grantId = null): void
    {
        if (Hash::check($password, $actor->password)) {
            return;
        }

        DB::table('platform_audit_logs')->insert([
            'actor_user_id' => $actor->getKey(),
            'target_tenant_id' => $tenantId,
            'action' => 'support_access.reauthentication_failed',
            'subject_type' => $grantId === null ? 'support_access_request' : 'support_access_grant',
            'subject_id' => $grantId === null ? null : (string) $grantId,
            'outcome' => 'failure',
            'reason_code' => 'support_access_reauthentication_failed',
            'before_json' => null,
            'after_json' => null,
            'request_id' => (string) $request->attributes->get('request_id', Str::uuid()),
            'occurred_at' => now('UTC'),
        ]);

        throw ValidationException::withMessages([
            'current_password' => __('platform.support_access.current_password_invalid'),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(Request $request, User $actor, int $tenantId, string $action, SupportAccessGrant $grant, string $outcome, string $reasonCode, ?array $before = null, ?array $after = null): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_user_id' => $actor->getKey(),
            'target_tenant_id' => $tenantId,
            'action' => $action,
            'subject_type' => 'support_access_grant',
            'subject_id' => (string) $grant->getKey(),
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => (string) $request->attributes->get('request_id', Str::uuid()),
            'occurred_at' => now('UTC'),
        ]);
    }
}
