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

class FamilyController extends Controller
{
    private const SEARCH_MAX_LENGTH = 100;

    private const RELATIONSHIP_TYPES = ['parent', 'mother', 'father', 'other'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $search = $this->normalizeSearch($request->query('q'));
        $families = $this->search($tenant, $search);

        return view('families.index', compact('actor', 'tenant', 'search', 'families'));
    }

    public function create(Request $request): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $search = $this->normalizeSearch($request->query('q'));
        $suggestedPhone = PhoneNormalizer::normalize($search) === null ? '' : $search;

        return view('families.create', compact('actor', 'tenant', 'suggestedPhone'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $this->trimInputs($request);

        $validator = Validator::make($request->all(), [
            'guardian_name' => ['required', 'string', 'min:2', 'max:190'],
            'phone' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (PhoneNormalizer::normalize($value) === null) {
                        $fail(__('families.validation.phone_invalid'));
                    }
                },
            ],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'preferred_locale' => ['required', 'string', Rule::in(['ar', 'en'])],
            'child_name' => ['required', 'string', 'min:2', 'max:190'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'relationship_type' => ['required', 'string', Rule::in(self::RELATIONSHIP_TYPES)],
        ], [
            'guardian_name.required' => __('families.validation.guardian_name_required'),
            'guardian_name.string' => __('families.validation.guardian_name_invalid'),
            'guardian_name.min' => __('families.validation.guardian_name_invalid'),
            'guardian_name.max' => __('families.validation.guardian_name_invalid'),
            'phone.required' => __('families.validation.phone_required'),
            'phone.string' => __('families.validation.phone_invalid'),
            'phone.max' => __('families.validation.phone_invalid'),
            'email.email' => __('families.validation.email_invalid'),
            'email.max' => __('families.validation.email_invalid'),
            'preferred_locale.required' => __('families.validation.locale_invalid'),
            'preferred_locale.in' => __('families.validation.locale_invalid'),
            'child_name.required' => __('families.validation.child_name_required'),
            'child_name.string' => __('families.validation.child_name_invalid'),
            'child_name.min' => __('families.validation.child_name_invalid'),
            'child_name.max' => __('families.validation.child_name_invalid'),
            'date_of_birth.date' => __('families.validation.date_of_birth_invalid'),
            'date_of_birth.before_or_equal' => __('families.validation.date_of_birth_invalid'),
            'relationship_type.required' => __('families.validation.relationship_type_required'),
            'relationship_type.in' => __('families.validation.relationship_type_invalid'),
        ]);

        if ($validator->fails()) {
            return $this->validationResponse($request, $validator);
        }

        $data = $validator->validated();
        $phone = PhoneNormalizer::normalize($data['phone']);

        $result = DB::transaction(function () use ($actor, $tenant, $data, $phone): array {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('create', Guardian::class);

            $duplicate = Guardian::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('phone_e164', $phone)
                ->select(['id'])
                ->first();

            if ($duplicate) {
                return ['duplicate_guardian_id' => $duplicate->getKey()];
            }

            $guardian = new Guardian;
            $guardian->tenant_id = $lockedTenant->getKey();
            $guardian->full_name = $data['guardian_name'];
            $guardian->phone_e164 = $phone;
            $guardian->email = $data['email'] ?? null;
            $guardian->preferred_locale = $data['preferred_locale'];
            $guardian->created_by_user_id = $lockedActor->getKey();
            $guardian->updated_by_user_id = $lockedActor->getKey();
            $guardian->save();

            $child = new Child;
            $child->tenant_id = $lockedTenant->getKey();
            $child->full_name = $data['child_name'];
            $child->date_of_birth = $data['date_of_birth'] ?? null;
            $child->created_by_user_id = $lockedActor->getKey();
            $child->updated_by_user_id = $lockedActor->getKey();
            $child->save();

            $guardian->children()->attach($child->getKey(), [
                'tenant_id' => $lockedTenant->getKey(),
                'relationship_type' => $data['relationship_type'],
                'is_active' => true,
                'created_by_user_id' => $lockedActor->getKey(),
                'updated_by_user_id' => $lockedActor->getKey(),
            ]);

            $now = now('UTC');
            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => null,
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'family.created',
                'subject_type' => 'guardian',
                'subject_id' => (string) $guardian->getKey(),
                'outcome' => 'success',
                'reason_code' => 'family_registration',
                'before_json' => null,
                'after_json' => json_encode([
                    'guardian_id' => (string) $guardian->getKey(),
                    'child_id' => (string) $child->getKey(),
                ], JSON_THROW_ON_ERROR),
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
            ]);

            return [
                'guardian_id' => $guardian->getKey(),
                'child_id' => $child->getKey(),
            ];
        });

        if (isset($result['duplicate_guardian_id'])) {
            return $this->duplicateResponse($request, (int) $result['duplicate_guardian_id']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('families.created'),
                'guardian_id' => (int) $result['guardian_id'],
                'child_id' => (int) $result['child_id'],
            ], 201);
        }

        return to_route('families.index', ['q' => $data['phone']])->with('success', __('families.created'));
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()
            ->whereKey($actor->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('viewAny', Guardian::class);

        return [$actor, $tenant];
    }

    private function normalizeSearch(mixed $value): string
    {
        return is_string($value) ? Str::substr(trim($value), 0, self::SEARCH_MAX_LENGTH) : '';
    }

    private function search(Tenant $tenant, string $search)
    {
        if ($search === '') {
            return collect();
        }

        $phone = PhoneNormalizer::normalize($search);
        $query = Guardian::query()
            ->where('guardians.tenant_id', $tenant->getKey())
            ->with(['children' => function ($children) use ($tenant): void {
                $children
                    ->where('children.tenant_id', $tenant->getKey())
                    ->where('guardian_child.tenant_id', $tenant->getKey())
                    ->where('guardian_child.is_active', true)
                    ->select(['children.id', 'children.tenant_id', 'children.full_name', 'children.date_of_birth']);
            }])
            ->select(['guardians.id', 'guardians.tenant_id', 'guardians.full_name', 'guardians.phone_e164', 'guardians.email', 'guardians.preferred_locale']);

        if ($phone !== null) {
            $query->where('guardians.phone_e164', $phone);
        } else {
            $pattern = $this->likePattern($search);
            $escape = DB::connection()->getDriverName() === 'mysql' ? '\\\\' : '\\';
            $query->whereHas('children', function ($children) use ($tenant, $pattern, $escape): void {
                $children
                    ->where('children.tenant_id', $tenant->getKey())
                    ->where('guardian_child.tenant_id', $tenant->getKey())
                    ->where('guardian_child.is_active', true)
                    ->whereRaw("children.full_name LIKE ? ESCAPE '{$escape}'", [$pattern]);
            });
        }

        return $query->orderBy('guardians.id')->limit(50)->get();
    }

    private function likePattern(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }

    private function trimInputs(Request $request): void
    {
        foreach (['guardian_name', 'phone', 'email', 'preferred_locale', 'child_name', 'date_of_birth', 'relationship_type'] as $key) {
            $value = $request->input($key);
            if (is_string($value)) {
                $request->merge([$key => trim($value)]);
            }
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }
    }

    private function duplicateResponse(Request $request, int $guardianId): JsonResponse|RedirectResponse
    {
        $message = __('families.duplicate');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'existing_guardian_id' => $guardianId,
                'existing_family_url' => route('families.index', ['q' => $request->input('phone')]),
            ], 409);
        }

        return redirect()
            ->route('families.create')
            ->withErrors(['phone' => $message])
            ->withInput()
            ->with('existing_guardian_id', $guardianId)
            ->with('existing_family_url', route('families.index', ['q' => $request->input('phone')]));
    }

    private function validationResponse(Request $request, $validator): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('families.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        return redirect()->route('families.create')->withErrors($validator)->withInput();
    }
}
