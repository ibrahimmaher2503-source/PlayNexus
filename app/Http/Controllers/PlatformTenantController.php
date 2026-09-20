<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantOwnerInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlatformTenantController extends Controller
{
    private const STATUSES = ['pending', 'active', 'suspended'];

    private const REASON_CODES = ['setup_change', 'access_review', 'correction'];

    public function index(Request $request): View
    {
        $tenants = Tenant::query()
            ->withCount('branches')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.tenants.index', [
            'actor' => $request->user(),
            'tenants' => $tenants,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $businessName = $request->input('business_name', $request->input('name'));
        $internalIdentifier = $request->input('internal_identifier');
        $planReference = $request->input('plan_reference');
        $ownerName = $request->input('initial_owner_name');
        $ownerEmail = $request->input('initial_owner_email');
        $idempotencyKey = $request->input('idempotency_key');

        $request->merge([
            'business_name' => is_string($businessName) ? trim($businessName) : $businessName,
            'internal_identifier' => is_string($internalIdentifier) ? strtoupper(trim($internalIdentifier)) : $internalIdentifier,
            'plan_reference' => is_string($planReference) ? (trim($planReference) ?: null) : $planReference,
            'initial_owner_name' => is_string($ownerName) ? trim($ownerName) : $ownerName,
            'initial_owner_email' => is_string($ownerEmail) ? Str::lower(trim($ownerEmail)) : $ownerEmail,
            'idempotency_key' => is_string($idempotencyKey) ? trim($idempotencyKey) : $idempotencyKey,
        ]);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:190'],
            'internal_identifier' => ['required', 'string', 'max:50', 'regex:/\A[A-Z0-9][A-Z0-9_-]*\z/'],
            'plan_reference' => ['nullable', 'string', 'max:190'],
            'initial_owner_name' => ['required', 'string', 'min:2', 'max:190'],
            'initial_owner_email' => ['required', 'string', 'email', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $payload = [
            'business_name' => $data['business_name'],
            'internal_identifier' => $data['internal_identifier'],
            'plan_reference' => $data['plan_reference'],
            'initial_owner_name' => $data['initial_owner_name'],
            'initial_owner_email' => $data['initial_owner_email'],
        ];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $actorId = (int) $request->user()->getAuthIdentifier();
        $requestId = (string) $request->attributes->get('request_id', Str::uuid());

        [$tenant, $invitationUrl, $created] = DB::transaction(function () use ($actorId, $data, $payloadHash, $requestId): array {
            $actor = User::query()
                ->whereKey($actorId)
                ->whereNull('tenant_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                DB::table('platform_admins')->where('user_id', $actor->getKey())->where('is_active', true)->exists(),
                403,
            );

            $existing = Tenant::query()
                ->where('provisioning_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->provisioning_payload_hash, $payloadHash)) {
                    throw new HttpException(409, __('platform.tenants.idempotency_conflict'));
                }

                return [$existing, null, false];
            }

            $errors = [];
            if (Tenant::query()->where('internal_identifier', $data['internal_identifier'])->exists()) {
                $errors['internal_identifier'] = __('validation.unique', ['attribute' => 'internal identifier']);
            }
            if (User::query()->where('email', $data['initial_owner_email'])->exists()) {
                $errors['initial_owner_email'] = __('validation.unique', ['attribute' => 'initial owner email']);
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $tenant = Tenant::query()->create([
                'name' => $data['business_name'],
                'legal_name' => $data['business_name'],
                'is_active' => false,
                'internal_identifier' => $data['internal_identifier'],
                'plan_reference' => $data['plan_reference'],
                'status' => 'pending',
                'provisioning_key' => $data['idempotency_key'],
                'provisioning_payload_hash' => $payloadHash,
            ]);

            $owner = new User;
            $owner->forceFill([
                'tenant_id' => $tenant->getKey(),
                'name' => $data['initial_owner_name'],
                'email' => $data['initial_owner_email'],
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => null,
                'status' => 'invited',
            ])->save();

            $now = now('UTC');
            DB::table('tenant_owners')->insert([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $owner->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->writePlatformAudit([
                'actor_user_id' => $actor->getKey(),
                'target_tenant_id' => $tenant->getKey(),
                'action' => 'tenant.provisioned',
                'subject_type' => 'tenant',
                'subject_id' => (string) $tenant->getKey(),
                'outcome' => 'success',
                'reason_code' => 'setup_change',
                'before_json' => null,
                'after_json' => json_encode([
                    'internal_identifier' => $tenant->internal_identifier,
                    'status' => $tenant->status,
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'occurred_at' => $now,
            ]);

            [$invitation, $token] = $this->issueOwnerInvitation($tenant, $owner, $actor, $now);
            $this->writePlatformAudit([
                'actor_user_id' => $actor->getKey(),
                'target_tenant_id' => $tenant->getKey(),
                'action' => 'tenant.owner_invitation.issued',
                'subject_type' => 'user',
                'subject_id' => (string) $owner->getKey(),
                'outcome' => 'success',
                'reason_code' => 'initial_owner_invitation',
                'before_json' => null,
                'after_json' => json_encode([
                    'invitation_id' => $invitation->getKey(),
                    'delivery_status' => $invitation->delivery_status,
                    'expires_at' => $invitation->expires_at->toISOString(),
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'occurred_at' => $now,
            ]);

            return [$tenant, route('owner-invitations.show', ['token' => $token]), true];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->tenantData($tenant),
                // This is only returned to the authenticated Super Admin at
                // issuance time. The raw token is never persisted or audited.
                'owner_invitation_url' => $invitationUrl,
                'created' => $created,
            ], $created ? 201 : 200);
        }

        $response = to_route('platform.tenants.index')->with('success', __('platform.tenants.provision_success'));

        if ($invitationUrl !== null) {
            $response->with('owner_invitation_url', $invitationUrl);
        }

        return $response;
    }

    public function show(Request $request, string $tenant): View
    {
        $tenant = Tenant::query()
            ->withCount(['branches', 'users'])
            ->with([
                'owners' => fn ($query) => $query->select(['users.id', 'users.tenant_id', 'users.name', 'users.email', 'users.status']),
                'currentSubscription.plan:id,code,name',
            ])
            ->findOrFail($tenant);

        $owner = $tenant->owners->first();
        $invitation = $owner
            ? TenantOwnerInvitation::query()->where('owner_user_id', $owner->getKey())->first()
            : null;
        $auditHistory = DB::table('platform_audit_logs')
            ->where('target_tenant_id', $tenant->getKey())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['action', 'subject_type', 'subject_id', 'outcome', 'reason_code', 'before_json', 'after_json', 'occurred_at']);

        return view('platform.tenants.show', compact('tenant', 'owner', 'invitation', 'auditHistory'));
    }

    public function reissueOwnerInvitation(Request $request, string $tenant): JsonResponse|RedirectResponse
    {
        $reason = $request->input('reason');
        $request->merge(['reason' => is_string($reason) ? trim($reason) : $reason]);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $actorId = (int) $request->user()->getAuthIdentifier();
        $requestId = (string) $request->attributes->get('request_id', Str::uuid());

        [$tenant, $invitationUrl] = DB::transaction(function () use ($actorId, $tenant, $requestId, $data): array {
            $actor = $this->lockedPlatformActor($actorId);
            $lockedTenant = Tenant::query()->whereKey($tenant)->lockForUpdate()->firstOrFail();
            $owner = User::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('status', 'invited')
                ->whereExists(fn ($query) => $query->selectRaw('1')->from('tenant_owners')
                    ->whereColumn('tenant_owners.user_id', 'users.id')
                    ->whereColumn('tenant_owners.tenant_id', 'users.tenant_id'))
                ->lockForUpdate()
                ->first();

            if (! $owner) {
                throw new HttpException(409, __('platform.tenants.owner_invitation_not_reissuable'));
            }

            $invitation = TenantOwnerInvitation::query()->where('owner_user_id', $owner->getKey())->lockForUpdate()->firstOrFail();
            $before = [
                'invitation_id' => $invitation->getKey(),
                'delivery_status' => $invitation->delivery_status,
                'expires_at' => $invitation->expires_at->toISOString(),
            ];
            [$invitation, $token] = $this->issueOwnerInvitation($lockedTenant, $owner, $actor, now('UTC'), $invitation);
            $this->writePlatformAudit([
                'actor_user_id' => $actor->getKey(),
                'target_tenant_id' => $lockedTenant->getKey(),
                'action' => 'tenant.owner_invitation.reissued',
                'subject_type' => 'user',
                'subject_id' => (string) $owner->getKey(),
                'outcome' => 'success',
                'reason_code' => 'owner_invitation_reissued',
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode([
                    'invitation_id' => $invitation->getKey(),
                    'delivery_status' => $invitation->delivery_status,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'reason' => $data['reason'],
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'occurred_at' => now('UTC'),
            ]);

            return [$lockedTenant, route('owner-invitations.show', ['token' => $token])];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->tenantData($tenant),
                'owner_invitation_url' => $invitationUrl,
            ]);
        }

        return to_route('platform.tenants.show', $tenant)
            ->with('success', __('platform.tenants.owner_invitation_reissued'))
            ->with('owner_invitation_url', $invitationUrl);
    }

    public function updateStatus(Request $request, string $tenant): JsonResponse|RedirectResponse
    {
        $reason = $request->input('reason');
        $request->merge(['reason' => is_string($reason) ? trim($reason) : $reason]);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'expected_status' => ['required', 'string', Rule::in(self::STATUSES)],
            'reason_code' => ['nullable', 'string', Rule::in(self::REASON_CODES)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $actorId = (int) $request->user()->getAuthIdentifier();
        $requestId = (string) $request->attributes->get('request_id', Str::uuid());

        [$tenant, $changed] = DB::transaction(function () use ($actorId, $data, $tenant, $requestId): array {
            $actor = $this->lockedPlatformActor($actorId);

            $lockedTenant = Tenant::query()->whereKey($tenant)->lockForUpdate()->firstOrFail();

            if ($lockedTenant->status !== $data['expected_status']) {
                throw new HttpException(409, __('platform.tenants.conflict'));
            }

            if ($lockedTenant->status === $data['status']) {
                return [$lockedTenant, false];
            }

            $before = ['status' => $lockedTenant->status];
            $after = ['status' => $data['status'], 'reason' => $data['reason']];
            $lockedTenant->forceFill([
                'status' => $data['status'],
                'is_active' => $data['status'] === 'active',
                'lock_version' => (int) $lockedTenant->lock_version + 1,
            ])->save();

            if ($data['status'] !== 'active') {
                User::query()->where('tenant_id', $lockedTenant->getKey())->increment('auth_version');
            }

            $now = now('UTC');
            $this->writePlatformAudit([
                'actor_user_id' => $actor->getKey(),
                'target_tenant_id' => $lockedTenant->getKey(),
                'action' => 'tenant.status.changed',
                'subject_type' => 'tenant',
                'subject_id' => (string) $lockedTenant->getKey(),
                'outcome' => 'success',
                'reason_code' => $data['reason_code'] ?? 'other',
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'occurred_at' => $now,
            ]);

            return [$lockedTenant, true];
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->tenantData($tenant), 'changed' => $changed]);
        }

        return to_route('platform.tenants.index')->with(
            $changed ? 'success' : 'status_message',
            __($changed ? 'platform.tenants.status_success' : 'platform.tenants.no_change'),
        );
    }

    /** @return array<string, mixed> */
    private function tenantData(Tenant $tenant): array
    {
        return $tenant->only([
            'id',
            'name',
            'legal_name',
            'internal_identifier',
            'plan_reference',
            'status',
            'is_active',
            'lock_version',
            'created_at',
            'updated_at',
        ]);
    }

    private function lockedPlatformActor(int $actorId): User
    {
        $actor = User::query()
            ->whereKey($actorId)
            ->whereNull('tenant_id')
            ->where('status', 'active')
            ->lockForUpdate()
            ->firstOrFail();
        abort_unless(
            DB::table('platform_admins')->where('user_id', $actor->getKey())->where('is_active', true)->exists(),
            403,
        );

        return $actor;
    }

    /** @return array{TenantOwnerInvitation, string} */
    private function issueOwnerInvitation(Tenant $tenant, User $owner, User $actor, \DateTimeInterface $now, ?TenantOwnerInvitation $invitation = null): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::instance($now)
            ->utc()
            ->addMinutes(max(1, (int) config('platform.owner_invitation_expiry_minutes', 1440)));
        $values = [
            'tenant_id' => $tenant->getKey(),
            'owner_user_id' => $owner->getKey(),
            'issued_by_user_id' => $actor->getKey(),
            'token_hash' => hash('sha256', $token),
            'destination_email' => $owner->email,
            'delivery_status' => 'manual_delivery_required',
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'revoked_at' => null,
        ];

        if ($invitation) {
            $invitation->forceFill($values)->save();
        } else {
            $invitation = TenantOwnerInvitation::query()->create($values);
        }

        return [$invitation->refresh(), $token];
    }

    /** @param array<string, mixed> $values */
    private function writePlatformAudit(array $values): void
    {
        DB::table('platform_audit_logs')->insert($values);
    }
}
