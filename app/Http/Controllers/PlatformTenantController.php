<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
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
            ->with(['owners' => fn ($query) => $query->select(['users.id', 'users.name', 'users.email'])])
            ->withCount('branches')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.tenants.index', [
            'actor' => $request->user(),
            'tenants' => $tenants,
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

        $tenant = DB::transaction(function () use ($actorId, $data, $payloadHash): Tenant {
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

                return $existing;
            }

            if (Tenant::query()->where('internal_identifier', $data['internal_identifier'])->exists()) {
                throw ValidationException::withMessages([
                    'internal_identifier' => __('validation.unique', ['attribute' => 'internal identifier']),
                ]);
            }

            if (User::query()->where('email', $data['initial_owner_email'])->exists()) {
                throw ValidationException::withMessages([
                    'initial_owner_email' => __('validation.unique', ['attribute' => 'initial owner email']),
                ]);
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
            DB::table('platform_audit_logs')->insert([
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
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
            ]);

            return $tenant;
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->tenantData($tenant)], 201);
        }

        return to_route('platform.tenants.index')->with('success', __('platform.tenants.created'));
    }

    public function updateStatus(Request $request, Tenant $tenant): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'expected_status' => ['required', 'string', Rule::in(self::STATUSES)],
            'reason_code' => ['required', 'string', Rule::in(self::REASON_CODES)],
        ]);
        $actorId = (int) $request->user()->getAuthIdentifier();

        [$tenant, $changed] = DB::transaction(function () use ($actorId, $data, $tenant): array {
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

            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedTenant->status !== $data['expected_status']) {
                throw new HttpException(409, __('platform.tenants.conflict'));
            }

            if ($lockedTenant->status === $data['status']) {
                return [$lockedTenant, false];
            }

            $before = ['status' => $lockedTenant->status];
            $after = ['status' => $data['status']];
            $lockedTenant->forceFill([
                'status' => $data['status'],
                'is_active' => $data['status'] === 'active',
                'lock_version' => (int) $lockedTenant->lock_version + 1,
            ])->save();

            $now = now('UTC');
            DB::table('platform_audit_logs')->insert([
                'actor_user_id' => $actor->getKey(),
                'target_tenant_id' => $lockedTenant->getKey(),
                'action' => 'tenant.status.changed',
                'subject_type' => 'tenant',
                'subject_id' => (string) $lockedTenant->getKey(),
                'outcome' => 'success',
                'reason_code' => $data['reason_code'],
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
            ]);

            return [$lockedTenant, true];
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->tenantData($tenant), 'changed' => $changed]);
        }

        return to_route('platform.tenants.index')->with(
            $changed ? 'success' : 'status_message',
            __($changed ? 'platform.tenants.status_updated' : 'platform.tenants.no_change'),
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
}
