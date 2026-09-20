<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantOwnerInvitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TenantOwnerInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->findUsableInvitation($token);

        if (! $invitation) {
            $this->recordDenied($request, $token, null, 'invalid_or_expired_invitation');
            abort(404);
        }

        return view('platform.invitations.accept', ['token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', Password::min(12), 'max:255', 'confirmed'],
        ]);

        $status = DB::transaction(function () use ($request, $token): bool {
            $invitation = TenantOwnerInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $this->isUsable($invitation)) {
                $this->recordDenied($request, $token, $invitation, 'invalid_or_expired_invitation');

                return false;
            }

            $owner = User::query()->whereKey($invitation->owner_user_id)->lockForUpdate()->first();
            $tenant = Tenant::query()->whereKey($invitation->tenant_id)->lockForUpdate()->first();
            $isOwner = $owner && DB::table('tenant_owners')
                ->where('tenant_id', $tenant?->getKey())
                ->where('user_id', $owner->getKey())
                ->exists();

            if (! $owner || ! $tenant || ! $isOwner || $owner->tenant_id !== $tenant->getKey() || $owner->status !== 'invited') {
                $this->recordDenied($request, $token, $invitation, 'owner_or_tenant_state_changed');

                return false;
            }

            $now = now('UTC');
            $owner->forceFill([
                'password' => Hash::make($request->string('password')->toString()),
                'status' => 'active',
                'email_verified_at' => $now,
                'remember_token' => Str::random(60),
                'auth_version' => (int) $owner->auth_version + 1,
            ])->save();
            $invitation->forceFill(['accepted_at' => $now])->save();
            DB::table('platform_audit_logs')->insert([
                'actor_user_id' => $owner->getKey(),
                'actor_type' => 'user',
                'target_tenant_id' => $tenant->getKey(),
                'action' => 'tenant.owner_invitation.accepted',
                'subject_type' => 'user',
                'subject_id' => (string) $owner->getKey(),
                'outcome' => 'success',
                'reason_code' => 'credential_setup_completed',
                'before_json' => json_encode(['status' => 'invited'], JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['status' => 'active'], JSON_THROW_ON_ERROR),
                'request_id' => (string) $request->attributes->get('request_id', Str::uuid()),
                'occurred_at' => $now,
            ]);

            return true;
        });

        if (! $status) {
            return to_route('login')->withErrors(['email' => __('passwords.invalid')]);
        }

        return to_route('login')->with('status', __('passwords.reset'));
    }

    private function findUsableInvitation(string $token): ?TenantOwnerInvitation
    {
        $invitation = TenantOwnerInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $this->isUsable($invitation) ? $invitation : null;
    }

    private function isUsable(?TenantOwnerInvitation $invitation): bool
    {
        return $invitation
            && $invitation->accepted_at === null
            && $invitation->revoked_at === null
            && $invitation->expires_at->isFuture();
    }

    private function recordDenied(Request $request, string $token, ?TenantOwnerInvitation $invitation, string $reason): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_user_id' => null,
            'actor_type' => 'system',
            'target_tenant_id' => $invitation?->tenant_id,
            'action' => 'security.owner_invitation_denied',
            'subject_type' => 'owner_invitation_token',
            'subject_id' => substr(hash('sha256', $token), 0, 26),
            'outcome' => 'failure',
            'reason_code' => $reason,
            'before_json' => null,
            'after_json' => null,
            'request_id' => (string) $request->attributes->get('request_id', Str::uuid()),
            'occurred_at' => now('UTC'),
        ]);
    }
}
