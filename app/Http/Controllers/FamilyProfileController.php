<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
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
    private const RELATIONSHIP_TYPES = ['parent', 'mother', 'father', 'other'];

    public function show(Request $request, Guardian $guardian): View|JsonResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $guardian = $this->guardianInTenant($guardian, $tenant);
        Gate::forUser($actor)->authorize('view', $guardian);
        $children = $this->activeChildren($guardian, $tenant)->get();

        if ($request->expectsJson()) {
            $data = $this->guardianData($guardian, $children);

            return response()->json(['guardian' => $data, 'children' => $data['children']]);
        }

        return view('families.show', compact('actor', 'tenant', 'guardian', 'children'));
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
                'guardian' => $this->guardianData($updated, collect()),
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
        Gate::forUser($actor)->authorize('manageChildren', $guardian);
        $data = $this->validateChild($request);

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $child, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('manageChildren', $lockedGuardian);
            $lockedChild = $this->lockedChildInGuardian($child, $lockedGuardian, $lockedTenant);

            if ((int) $lockedChild->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedChild->lock_version];
            }

            $fields = [
                'full_name' => $data['child_name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
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
                'child' => $this->childData($result['child']),
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
        Gate::forUser($actor)->authorize('manageChildren', $guardian);
        $data = $this->validateNewChild($request);

        $result = DB::transaction(function () use ($actor, $tenant, $guardian, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedGuardian = $this->lockedGuardian($guardian, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('manageChildren', $lockedGuardian);
            if ((int) $lockedGuardian->lock_version !== (int) $data['expected_version']) {
                return ['conflict_version' => (int) $lockedGuardian->lock_version];
            }

            $child = new Child;
            $child->forceFill([
                'tenant_id' => $lockedTenant->getKey(),
                'full_name' => $data['child_name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'created_by_user_id' => $lockedActor->getKey(),
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => 1,
            ])->save();
            $lockedGuardian->children()->attach($child->getKey(), [
                'tenant_id' => $lockedTenant->getKey(),
                'relationship_type' => $data['relationship_type'],
                'is_active' => true,
                'created_by_user_id' => $lockedActor->getKey(),
                'updated_by_user_id' => $lockedActor->getKey(),
            ]);
            $lockedGuardian->forceFill([
                'updated_by_user_id' => $lockedActor->getKey(),
                'lock_version' => (int) $lockedGuardian->lock_version + 1,
            ])->save();
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
                'child' => $this->childData($result['child']),
                'guardian_id' => $result['guardian_id'],
            ], 201);
        }

        return to_route('families.show', $guardian)->with('success', __('families.child_added'));
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
            ->select(['children.id', 'children.tenant_id', 'children.full_name', 'children.date_of_birth', 'children.status', 'children.lock_version'])
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
        $this->trim($request, ['child_name', 'date_of_birth']);

        return Validator::make($request->all(), [
            'child_name' => ['required', 'string', 'min:2', 'max:190'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();
    }

    /** @return array<string, mixed> */
    private function validateNewChild(Request $request): array
    {
        $this->trim($request, ['child_name', 'date_of_birth', 'relationship_type']);

        return Validator::make($request->all(), [
            'child_name' => ['required', 'string', 'min:2', 'max:190'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'relationship_type' => ['required', 'string', Rule::in(self::RELATIONSHIP_TYPES)],
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
        ];
    }

    /** @return array<string, mixed> */
    private function guardianData(Guardian $guardian, $children): array
    {
        return [
            'id' => (int) $guardian->getKey(),
            'full_name' => $guardian->full_name,
            'phone_e164' => $guardian->phone_e164,
            'email' => $guardian->email,
            'preferred_locale' => $guardian->preferred_locale,
            'status' => $guardian->status,
            'lock_version' => (int) $guardian->lock_version,
            'children' => collect($children)->map(fn (Child $child): array => $this->childData($child))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function childData(Child $child): array
    {
        return [
            'id' => (int) $child->getKey(),
            'full_name' => $child->full_name,
            'date_of_birth' => $child->date_of_birth?->format('Y-m-d'),
            'status' => $child->status,
            'lock_version' => (int) $child->lock_version,
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
}
