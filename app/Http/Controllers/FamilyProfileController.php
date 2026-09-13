<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FamilyConsentRecorder;
use App\Support\FamilyPresenter;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FamilyProfileController extends Controller
{
    private const RELATIONSHIP_TYPES = ['parent', 'mother', 'father', 'legal_guardian'];

    public function show(Request $request, Guardian $guardian): View|JsonResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        Gate::forUser($actor)->authorize('view', $guardian);
        $children = $this->activeChildren($guardian, $tenant)->get();
        $presenter = new FamilyPresenter($actor);

        if ($request->expectsJson()) {
            $data = $presenter->guardian($guardian, $children);

            return response()->json(['guardian' => $data, 'children' => $data['children']]);
        }

        $canUpdateGuardian = Gate::forUser($actor)->allows('update', $guardian);
        $canCreateChild = Gate::forUser($actor)->allows('createChild', $guardian);
        $canUpdateChild = Gate::forUser($actor)->allows('updateChild', $guardian);
        $canManageConsent = Gate::forUser($actor)->allows('manageConsent', $guardian);
        $canManageRelationships = Gate::forUser($actor)->allows('manageRelationships', $guardian);
        $noticeVersion = FamilyController::NOTICE_VERSION;
        $consentStatuses = DB::table('family_consent_events')
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('child_id', $children->pluck('id'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('child_id')
            ->map(fn ($events) => $events->groupBy('consent_type')->map(fn ($typed) => $typed->first()->status));
        $relationships = DB::table('guardian_child')
            ->join('guardians', function ($join): void {
                $join->on('guardians.tenant_id', '=', 'guardian_child.tenant_id')
                    ->on('guardians.id', '=', 'guardian_child.guardian_id');
            })
            ->where('guardian_child.tenant_id', $tenant->getKey())
            ->whereIn('guardian_child.child_id', $children->pluck('id'))
            ->select([
                'guardian_child.child_id',
                'guardian_child.guardian_id',
                'guardian_child.relationship_type',
                'guardian_child.can_consent',
                'guardian_child.can_check_out',
                'guardian_child.is_active',
                'guardians.full_name',
                'guardians.phone_e164',
            ])
            ->orderBy('guardian_child.guardian_id')
            ->get()
            ->groupBy('child_id');

        return view('families.show', compact('actor', 'tenant', 'guardian', 'children', 'canUpdateGuardian', 'canCreateChild', 'canUpdateChild', 'canManageConsent', 'canManageRelationships', 'noticeVersion', 'consentStatuses', 'relationships'));
    }

    public function update(Request $request, Guardian $guardian): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        Gate::forUser($actor)->authorize('update', $guardian);
        $data = $this->validateGuardian($request);
        $phone = PhoneNormalizer::normalize($data['phone']);

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $data, $phone): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('update', $lockedGuardian);

            if ((int) $lockedGuardian->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedGuardian->lock_version];
            }

            $duplicate = Guardian::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('phone_e164', $phone)
                ->where('id', '<>', $lockedGuardian->getKey())
                ->select('id')
                ->first();
            if ($duplicate) {
                return ['duplicate_guardian_id' => (int) $duplicate->getKey()];
            }

            $fields = [
                'full_name' => $data['guardian_name'],
                'phone_e164' => $phone,
                'email' => $data['email'] ?? null,
                'preferred_locale' => $data['preferred_locale'],
            ];
            $before = $this->guardianState($lockedGuardian);
            $changedFields = array_keys(array_filter($fields, fn (mixed $value, string $field): bool => $before[$field] !== $value, ARRAY_FILTER_USE_BOTH));
            if ($changedFields === []) {
                return ['changed' => false, 'guardian' => $lockedGuardian];
            }

            $nextVersion = (int) $lockedGuardian->lock_version + 1;
            $lockedGuardian->forceFill($fields + [
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => $nextVersion,
            ])->save();
            $this->audit($lockedActor, $lockedTenant, 'family.guardian.updated', 'guardian', $lockedGuardian->getKey(), [
                'guardian_id' => (string) $lockedGuardian->getKey(),
                'changed_fields' => $changedFields,
            ], null);

            return ['changed' => true, 'guardian' => $lockedGuardian->fresh(), 'lock_version' => $nextVersion];
        });

        if (isset($result['conflict_version'])) {
            return $this->conflictResponse($request, (int) $result['conflict_version']);
        }
        if (isset($result['duplicate_guardian_id'])) {
            return $this->duplicateResponse($request, (int) $result['duplicate_guardian_id']);
        }

        $message = $result['changed'] ? __('families.updated') : __('families.no_change');
        if ($request->expectsJson()) {
            $updated = $result['guardian'];

            return response()->json([
                'message' => $message,
                'changed' => $result['changed'],
                'lock_version' => (int) $updated->lock_version,
                'guardian' => (new FamilyPresenter($actor))->guardian($updated, collect()),
            ]);
        }

        return to_route('families.show', $guardian)->with(
            $result['changed'] ? 'success' : 'status_message',
            $message,
        );
    }

    public function updateChild(Request $request, Guardian $guardian, Child $child): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        $child = $this->childInGuardian($child, $guardian, $tenant);
        Gate::forUser($actor)->authorize('updateChild', $guardian);
        $data = $this->validateChild($request);

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $child, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('updateChild', $lockedGuardian);
            $lockedChild = $this->lockedChildInGuardian($child, $lockedGuardian, $lockedTenant);

            if ((int) $lockedChild->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedChild->lock_version];
            }

            $fields = [
                'full_name' => $data['child_name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? $lockedChild->emergency_contact_name ?? $lockedGuardian->full_name,
                'emergency_contact_phone_e164' => PhoneNormalizer::normalize($data['emergency_contact_phone'] ?? '') ?? $lockedChild->emergency_contact_phone_e164 ?? $lockedGuardian->phone_e164,
                'safety_notes_encrypted' => array_key_exists('safety_notes', $data) ? ($data['safety_notes'] ?: null) : $lockedChild->safety_notes_encrypted,
            ];
            $before = $this->childState($lockedChild);
            $changedFields = array_keys(array_filter($fields, fn (mixed $value, string $field): bool => $before[$field] !== $value, ARRAY_FILTER_USE_BOTH));
            if ($changedFields === []) {
                return ['changed' => false, 'child' => $lockedChild];
            }

            $nextVersion = (int) $lockedChild->lock_version + 1;
            $lockedChild->forceFill($fields + [
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => $nextVersion,
            ])->save();
            $this->audit($lockedActor, $lockedTenant, 'family.child.updated', 'child', $lockedChild->getKey(), [
                'child_id' => (string) $lockedChild->getKey(),
                'changed_fields' => $changedFields,
            ], null);

            return ['changed' => true, 'child' => $lockedChild->fresh(), 'lock_version' => $nextVersion];
        });

        if (isset($result['conflict_version'])) {
            return $this->conflictResponse($request, (int) $result['conflict_version']);
        }

        $message = $result['changed'] ? __('families.updated') : __('families.no_change');
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'changed' => $result['changed'],
                'lock_version' => (int) $result['child']->lock_version,
                'child' => (new FamilyPresenter($actor))->child($result['child']),
            ]);
        }

        return to_route('families.show', $guardian)->with(
            $result['changed'] ? 'success' : 'status_message',
            $message,
        );
    }

    public function storeChild(Request $request, Guardian $guardian): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        Gate::forUser($actor)->authorize('createChild', $guardian);
        $data = $this->validateNewChild($request);
        $requestId = (string) $request->attributes->get('request_id', Str::uuid());

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $data, $requestId): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('createChild', $lockedGuardian);
            if ((int) $lockedGuardian->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedGuardian->lock_version];
            }

            $child = new Child;
            $child->forceFill([
                'tenant_id' => $lockedTenant->getKey(),
                'full_name' => $data['child_name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? $lockedGuardian->full_name,
                'emergency_contact_phone_e164' => PhoneNormalizer::normalize($data['emergency_contact_phone'] ?? '') ?? $lockedGuardian->phone_e164,
                'safety_notes_encrypted' => $data['safety_notes'] ?? null,
                'created_by_user_id' => $lockedActor->getKey(),
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => 1,
            ])->save();
            $lockedGuardian->children()->attach($child->getKey(), [
                'tenant_id' => $lockedTenant->getKey(),
                'relationship_type' => $data['relationship_type'] === 'parent' ? 'legal_guardian' : $data['relationship_type'],
                'can_consent' => true,
                'can_check_out' => true,
                'is_primary' => true,
                'verification_method' => 'registered_phone_last_four',
                'verified_at' => now('UTC'),
                'verified_by_user_id' => $lockedActor->getKey(),
                'is_active' => true,
                'created_by_user_id' => $lockedActor->getKey(),
                'updated_by_user_id' => $lockedActor->getKey(),
            ]);
            $lockedGuardian->forceFill([
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => (int) $lockedGuardian->lock_version + 1,
            ])->save();
            FamilyConsentRecorder::record($lockedActor, $lockedTenant, $lockedGuardian, $child, 'child_data', 'granted', $lockedGuardian->preferred_locale, $requestId);
            $this->audit($lockedActor, $lockedTenant, 'family.child.added', 'child', $child->getKey(), [
                'guardian_id' => (string) $lockedGuardian->getKey(),
                'child_id' => (string) $child->getKey(),
                'changed_fields' => ['child_name', 'date_of_birth', 'relationship_type'],
            ], null);

            return ['child' => $child->fresh(), 'guardian_id' => (int) $lockedGuardian->getKey()];
        });

        if (isset($result['conflict_version'])) {
            return $this->conflictResponse($request, (int) $result['conflict_version']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('families.child_added'),
                'child' => (new FamilyPresenter($actor))->child($result['child']),
                'guardian_id' => $result['guardian_id'],
            ], 201);
        }

        return to_route('families.show', $guardian)->with('success', __('families.child_added'));
    }

    public function withdrawConsent(Request $request, Guardian $guardian, Child $child): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        $child = $this->childInGuardian($child, $guardian, $tenant);
        Gate::forUser($actor)->authorize('manageConsent', $guardian);
        $data = Validator::make($request->all(), [
            'consent_type' => ['required', Rule::in(['child_data', 'marketing'])],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();
        $requestId = (string) $request->attributes->get('request_id', Str::uuid());

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $child, $data, $requestId): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('manageConsent', $lockedGuardian);
            $lockedChild = $this->lockedChildInGuardian($child, $lockedGuardian, $lockedTenant);

            if ((int) $lockedChild->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedChild->lock_version];
            }

            FamilyConsentRecorder::record($lockedActor, $lockedTenant, $lockedGuardian, $lockedChild, $data['consent_type'], 'withdrawn', $lockedGuardian->preferred_locale, $requestId);

            if ($data['consent_type'] === 'child_data') {
                $lockedChild->forceFill([
                    'status' => 'restricted',
                    'updated_by_user_id' => $lockedActor->getKey(),
                    'lock_version' => (int) $lockedChild->lock_version + 1,
                ])->save();
            }

            $this->audit($lockedActor, $lockedTenant, 'family.consent.withdrawn', 'child', $lockedChild->getKey(), [
                'child_id' => (string) $lockedChild->getKey(),
                'consent_type' => $data['consent_type'],
            ], null);

            return ['child' => $lockedChild->fresh()];
        });

        if (isset($result['conflict_version'])) {
            return $this->conflictResponse($request, (int) $result['conflict_version']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('families.consent_withdrawn')]);
        }

        return to_route('families.show', $guardian)->with('success', __('families.consent_withdrawn'));
    }

    public function storeRelationship(Request $request, Guardian $guardian, Child $child): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        $child = $this->childInGuardian($child, $guardian, $tenant);
        Gate::forUser($actor)->authorize('manageRelationships', $guardian);
        $this->trim($request, ['guardian_phone', 'relationship_type', 'verification_method', 'verification_value']);
        $data = Validator::make($request->all(), [
            'guardian_phone' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if (PhoneNormalizer::normalize($value) === null) {
                    $fail(__('families.validation.phone_invalid'));
                }
            }],
            'relationship_type' => ['required', Rule::in(['mother', 'father', 'legal_guardian', 'authorized_pickup', 'other'])],
            'can_check_out' => ['nullable', 'boolean'],
            'verification_method' => ['required', Rule::in(['registered_phone_last_four'])],
            'verification_value' => ['required', 'digits:4'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();
        $phone = PhoneNormalizer::normalize($data['guardian_phone']);

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $child, $data, $phone): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('manageRelationships', $lockedGuardian);
            $lockedChild = $this->lockedChildInGuardian($child, $lockedGuardian, $lockedTenant);

            if ((int) $lockedChild->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedChild->lock_version];
            }

            $related = Guardian::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('status', 'active')
                ->where('phone_e164', $phone)
                ->lockForUpdate()
                ->first();
            if (! $related) {
                return ['not_found' => true];
            }
            if (! hash_equals(substr($related->phone_e164, -4), (string) $data['verification_value'])) {
                return ['verification_failed' => true];
            }

            $canConsent = in_array($data['relationship_type'], ['mother', 'father', 'legal_guardian'], true);
            $now = now('UTC');
            $values = [
                'relationship_type' => $data['relationship_type'],
                'can_consent' => $canConsent,
                'can_check_out' => (bool) ($data['can_check_out'] ?? false),
                'verification_method' => $data['verification_method'],
                'verified_at' => $now,
                'verified_by_user_id' => $lockedActor->getKey(),
                'is_active' => true,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'updated_by_user_id' => $lockedActor->getKey(),
                'updated_at' => $now,
            ];
            $exists = DB::table('guardian_child')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('guardian_id', $related->getKey())
                ->where('child_id', $lockedChild->getKey())
                ->exists();

            if ($exists) {
                DB::table('guardian_child')
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->where('guardian_id', $related->getKey())
                    ->where('child_id', $lockedChild->getKey())
                    ->update($values);
            } else {
                DB::table('guardian_child')->insert($values + [
                    'tenant_id' => $lockedTenant->getKey(),
                    'guardian_id' => $related->getKey(),
                    'child_id' => $lockedChild->getKey(),
                    'is_primary' => false,
                    'created_by_user_id' => $lockedActor->getKey(),
                    'created_at' => $now,
                ]);
            }

            $lockedChild->forceFill([
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => (int) $lockedChild->lock_version + 1,
            ])->save();
            $this->audit($lockedActor, $lockedTenant, 'family.relationship.saved', 'child', $lockedChild->getKey(), [
                'child_id' => (string) $lockedChild->getKey(),
                'guardian_id' => (string) $related->getKey(),
                'relationship_type' => $data['relationship_type'],
                'can_check_out' => (bool) ($data['can_check_out'] ?? false),
            ], null);

            return ['child_version' => (int) $lockedChild->lock_version];
        });

        if (isset($result['conflict_version'])) {
            return $this->conflictResponse($request, (int) $result['conflict_version']);
        }
        if (isset($result['not_found'])) {
            return $this->relationshipNotFoundResponse($request);
        }
        if (isset($result['verification_failed'])) {
            return $this->relationshipVerificationResponse($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('families.relationship_saved'), 'child_version' => $result['child_version']])
            : to_route('families.show', $guardian)->with('success', __('families.relationship_saved'));
    }

    public function revokeRelationship(Request $request, Guardian $guardian, Child $child, Guardian $relatedGuardian): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        $child = $this->childInGuardian($child, $guardian, $tenant);
        Gate::forUser($actor)->authorize('manageRelationships', $guardian);
        $data = Validator::make($request->all(), ['expected_version' => ['required', 'integer', 'min:1']])->validate();

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $child, $relatedGuardian, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('manageRelationships', $lockedGuardian);
            $lockedChild = $this->lockedChildInGuardian($child, $lockedGuardian, $lockedTenant);

            if ((int) $lockedChild->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedChild->lock_version];
            }

            $target = DB::table('guardian_child')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('child_id', $lockedChild->getKey())
                ->where('guardian_id', $relatedGuardian->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $target) {
                abort(404);
            }

            $isProtectedGuardian = (bool) $target->can_consent && (bool) $target->can_check_out;
            if ($isProtectedGuardian) {
                $remaining = DB::table('guardian_child')
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->where('child_id', $lockedChild->getKey())
                    ->where('guardian_id', '<>', $relatedGuardian->getKey())
                    ->where('is_active', true)
                    ->where('can_consent', true)
                    ->where('can_check_out', true)
                    ->lockForUpdate()
                    ->exists();
                if (! $remaining) {
                    return ['last_guardian' => true];
                }
            }

            $now = now('UTC');
            DB::table('guardian_child')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('child_id', $lockedChild->getKey())
                ->where('guardian_id', $relatedGuardian->getKey())
                ->update([
                    'is_active' => false,
                    'revoked_at' => $now,
                    'revoked_by_user_id' => $lockedActor->getKey(),
                    'updated_by_user_id' => $lockedActor->getKey(),
                    'updated_at' => $now,
                ]);
            $lockedChild->forceFill([
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => (int) $lockedChild->lock_version + 1,
            ])->save();
            $this->audit($lockedActor, $lockedTenant, 'family.relationship.revoked', 'child', $lockedChild->getKey(), [
                'child_id' => (string) $lockedChild->getKey(),
                'guardian_id' => (string) $relatedGuardian->getKey(),
            ], null);

            return ['child_version' => (int) $lockedChild->lock_version];
        });

        if (isset($result['conflict_version'])) {
            return $this->conflictResponse($request, (int) $result['conflict_version']);
        }
        if (isset($result['last_guardian'])) {
            return $this->lastGuardianResponse($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('families.relationship_revoked'), 'child_version' => $result['child_version']])
            : to_route('families.show', $guardian)->with('success', __('families.relationship_revoked'));
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', Guardian::class);

        return [$actor, $tenant];
    }

    /** @return array{User, Tenant} */
    private function lockedContext(User $actor, Tenant $tenant): array
    {
        $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
        $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
        Gate::forUser($lockedActor)->authorize('viewAny', Guardian::class);

        return [$lockedActor, $lockedTenant];
    }

    private function guardianInTenant(Guardian $guardian, Tenant $tenant): Guardian
    {
        return Guardian::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereKey($guardian->getKey())
            ->firstOrFail();
    }

    private function lockedGuardian(Guardian $guardian, Tenant $tenant): Guardian
    {
        return Guardian::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereKey($guardian->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function childInGuardian(Child $child, Guardian $guardian, Tenant $tenant): Child
    {
        $child = Child::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereKey($child->getKey())
            ->firstOrFail();

        abort_unless($this->activeLinkExists($guardian, $child, $tenant), 404);

        return $child;
    }

    private function lockedChildInGuardian(Child $child, Guardian $guardian, Tenant $tenant): Child
    {
        $child = Child::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereKey($child->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless($this->activeLinkExists($guardian, $child, $tenant), 404);

        return $child;
    }

    private function activeLinkExists(Guardian $guardian, Child $child, Tenant $tenant): bool
    {
        return DB::table('guardian_child')
            ->where('tenant_id', $tenant->getKey())
            ->where('guardian_id', $guardian->getKey())
            ->where('child_id', $child->getKey())
            ->where('is_active', true)
            ->exists();
    }

    private function activeChildren(Guardian $guardian, Tenant $tenant)
    {
        return $guardian->children()
            ->where('children.tenant_id', $tenant->getKey())
            ->where('children.status', 'active')
            ->wherePivot('tenant_id', $tenant->getKey())
            ->wherePivot('is_active', true)
            ->select(['children.id', 'children.tenant_id', 'children.full_name', 'children.date_of_birth', 'children.emergency_contact_name', 'children.emergency_contact_phone_e164', 'children.safety_notes_encrypted', 'children.status', 'children.lock_version'])
            ->orderBy('children.id');
    }

    /** @return array<string, mixed> */
    private function validateGuardian(Request $request): array
    {
        $this->trim($request, ['guardian_name', 'phone', 'email', 'preferred_locale']);
        $validator = Validator::make($request->all(), [
            'guardian_name' => ['required', 'string', 'min:2', 'max:190'],
            'phone' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if (PhoneNormalizer::normalize($value) === null) {
                    $fail(__('families.validation.phone_invalid'));
                }
            }],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'preferred_locale' => ['required', 'string', Rule::in(['ar', 'en'])],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        return $validator->validate() + ['email' => null];
    }

    /** @return array<string, mixed> */
    private function validateChild(Request $request): array
    {
        $this->trim($request, ['child_name', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone', 'safety_notes']);

        return Validator::make($request->all(), [
            'child_name' => ['required', 'string', 'min:2', 'max:190'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'emergency_contact_name' => ['nullable', 'string', 'min:2', 'max:190'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && PhoneNormalizer::normalize($value) === null) {
                    $fail(__('families.validation.emergency_phone_invalid'));
                }
            }],
            'safety_notes' => ['nullable', 'string', 'max:1000'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();
    }

    /** @return array<string, mixed> */
    private function validateNewChild(Request $request): array
    {
        $this->trim($request, ['child_name', 'date_of_birth', 'relationship_type', 'emergency_contact_name', 'emergency_contact_phone', 'safety_notes', 'notice_version']);

        return Validator::make($request->all(), [
            'child_name' => ['required', 'string', 'min:2', 'max:190'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'relationship_type' => ['required', 'string', Rule::in(self::RELATIONSHIP_TYPES)],
            'emergency_contact_name' => ['nullable', 'string', 'min:2', 'max:190'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && PhoneNormalizer::normalize($value) === null) {
                    $fail(__('families.validation.emergency_phone_invalid'));
                }
            }],
            'safety_notes' => ['nullable', 'string', 'max:1000'],
            'child_data_consent' => ['accepted'],
            'notice_version' => ['required', Rule::in([FamilyController::NOTICE_VERSION])],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();
    }

    private function trim(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }
    }

    /** @return array<string, mixed> */
    private function guardianState(Guardian $guardian): array
    {
        return [
            'full_name' => $guardian->full_name,
            'phone_e164' => $guardian->phone_e164,
            'email' => $guardian->email,
            'preferred_locale' => $guardian->preferred_locale,
        ];
    }

    /** @return array<string, mixed> */
    private function childState(Child $child): array
    {
        return [
            'full_name' => $child->full_name,
            'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
            'emergency_contact_name' => $child->emergency_contact_name,
            'emergency_contact_phone_e164' => $child->emergency_contact_phone_e164,
            'safety_notes_encrypted' => $child->safety_notes_encrypted,
        ];
    }

    private function audit(User $actor, Tenant $tenant, string $action, string $subjectType, int $subjectId, array $after, ?array $before): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(),
            'branch_id' => null,
            'actor_user_id' => $actor->getKey(),
            'actor_type' => 'user',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectId,
            'outcome' => 'success',
            'reason_code' => 'family_profile_maintenance',
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now('UTC'),
        ]);
    }

    private function conflictResponse(Request $request, int $version): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('families.conflict'), 'current_version' => $version], 409);
        }

        return back()->withErrors(['expected_version' => __('families.conflict')])->withInput();
    }

    private function duplicateResponse(Request $request, int $guardianId): JsonResponse|RedirectResponse
    {
        $url = route('families.show', ['guardian' => $guardianId]);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('families.duplicate'),
                'existing_guardian_id' => $guardianId,
                'existing_family_url' => $url,
            ], 409);
        }

        return back()->withErrors(['phone' => __('families.duplicate')])->withInput()->with('existing_family_url', $url);
    }

    private function relationshipNotFoundResponse(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('families.relationship_guardian_not_found')], 422);
        }

        return back()->withErrors(['guardian_phone' => __('families.relationship_guardian_not_found')])->withInput();
    }

    private function lastGuardianResponse(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('families.last_guardian_required')], 422);
        }

        return back()->withErrors(['relationship' => __('families.last_guardian_required')])->withInput();
    }

    private function relationshipVerificationResponse(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('families.relationship_verification_failed')], 422);
        }

        return back()->withErrors(['verification_value' => __('families.relationship_verification_failed')])->withInput();
    }
}
