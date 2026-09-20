<?php

namespace App\Http\Controllers;

use App\Models\CustomRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoleManagementController extends Controller
{
    private const PERMISSION = 'branches.view';

    public function index(Request $request): View
    {
        [, $tenant] = $this->ownerContext($request);
        $roles = CustomRole::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('permissions')
            ->select('custom_roles.*')
            ->selectSub(
                DB::table('branch_user')
                    ->selectRaw('COUNT(DISTINCT branch_user.user_id)')
                    ->whereColumn('branch_user.role', 'custom_roles.code')
                    ->where('branch_user.tenant_id', $tenant->getKey()),
                'assigned_staff_count',
            )
            ->selectSub(
                DB::table('branch_user')
                    ->selectRaw('COUNT(DISTINCT branch_user.branch_id)')
                    ->whereColumn('branch_user.role', 'custom_roles.code')
                    ->where('branch_user.tenant_id', $tenant->getKey())
                    ->where('branch_user.is_active', true),
                'assigned_branch_count',
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('roles.index', compact('tenant', 'roles'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->ownerContext($request);
        $validated = $this->validateRole($request, $tenant);

        $role = DB::transaction(function () use ($actor, $tenant, $validated): CustomRole {
            [$lockedActor, $lockedTenant] = $this->lockedOwnerContext($actor, $tenant);
            $role = new CustomRole([
                'tenant_id' => $lockedTenant->getKey(),
                'name' => $validated['name'],
                'code' => $this->nextCode($lockedTenant, $validated['name']),
                'lock_version' => 1,
            ]);
            $role->save();
            $this->syncPermission($role, $validated['permissions']);

            $this->audit($lockedActor, $lockedTenant, 'custom_role.created', $role, null, $role->snapshot());

            return $role;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('roles.created'), 'role' => $role->load('permissions')], 201);
        }

        return to_route('roles.index')->with('success', __('roles.created'));
    }

    public function update(Request $request, CustomRole $role): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->ownerContext($request);
        $target = CustomRole::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($role->getKey())
            ->firstOrFail();
        $validated = $this->validateRole($request, $tenant, $target);

        $updated = DB::transaction(function () use ($actor, $tenant, $target, $validated): CustomRole {
            [$lockedActor, $lockedTenant] = $this->lockedOwnerContext($actor, $tenant);
            $lockedRole = CustomRole::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();
            abort_unless($lockedRole, 404);

            if ((int) $lockedRole->lock_version !== $validated['expected_lock_version']) {
                throw new HttpException(409, __('roles.conflict'));
            }

            $before = $lockedRole->snapshot();
            $lockedRole->name = $validated['name'];
            $lockedRole->lock_version++;
            $lockedRole->save();

            $this->syncPermission($lockedRole, $validated['permissions']);
            $this->audit($lockedActor, $lockedTenant, 'custom_role.updated', $lockedRole, $before, $lockedRole->snapshot());

            return $lockedRole;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('roles.updated'), 'role' => $updated->load('permissions')]);
        }

        return to_route('roles.index')->with('success', __('roles.updated'));
    }

    private function validateRole(Request $request, Tenant $tenant, ?CustomRole $role = null): array
    {
        $name = $request->input('name');
        $request->merge(['name' => is_string($name) ? trim($name) : $name]);
        $rules = [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('custom_roles', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->getKey()))
                    ->ignore($role?->getKey()),
            ],
            'permissions' => ['sometimes', 'array', 'max:1'],
            'permissions.*' => [Rule::in([self::PERMISSION])],
        ];
        if ($role) {
            $rules['expected_lock_version'] = ['required', 'integer', 'min:1'];
        }

        $validator = Validator::make($request->all(), $rules, [
            'name.required' => __('roles.validation.name_required'),
            'name.string' => __('roles.validation.name_invalid'),
            'name.min' => __('roles.validation.name_invalid'),
            'name.max' => __('roles.validation.name_invalid'),
            'name.unique' => __('roles.validation.name_taken'),
            'permissions.array' => __('roles.validation.permission_invalid'),
            'permissions.size' => __('roles.validation.permission_invalid'),
            'permissions.*.in' => __('roles.validation.permission_invalid'),
            'expected_lock_version.required' => __('roles.validation.expected_lock_version_required'),
            'expected_lock_version.integer' => __('roles.validation.expected_lock_version_invalid'),
            'expected_lock_version.min' => __('roles.validation.expected_lock_version_invalid'),
        ]);
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                abort(response()->json(['message' => __('roles.validation_failed'), 'errors' => $validator->errors()], 422));
            }

            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $data['permissions'] = array_values($data['permissions'] ?? []);

        return $data;
    }

    private function syncPermission(CustomRole $role, array $permissions): void
    {
        $role->permissions()->delete();
        if (in_array(self::PERMISSION, $permissions, true)) {
            $role->permissions()->create(['permission' => self::PERMISSION]);
        }
    }

    private function nextCode(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name, '_');
        $base = $base !== '' ? substr($base, 0, 40) : 'custom_role';
        $code = $base;
        $suffix = 2;
        while (CustomRole::query()->where('tenant_id', $tenant->getKey())->where('code', $code)->exists()) {
            $ending = '_'.$suffix++;
            $code = substr($base, 0, 50 - strlen($ending)).$ending;
        }

        return $code;
    }

    /** @return array{User, Tenant} */
    private function ownerContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('view', $tenant);

        return [$actor, $tenant];
    }

    /** @return array{User, Tenant} */
    private function lockedOwnerContext(User $actor, Tenant $tenant): array
    {
        $lockedTenant = Tenant::query()
            ->whereKey($tenant->getKey())
            ->where('is_active', true)
            ->lockForUpdate()
            ->firstOrFail();
        $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
        Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

        return [$lockedActor, $lockedTenant];
    }

    private function audit(User $actor, Tenant $tenant, string $action, CustomRole $role, ?array $before, array $after): void
    {
        $now = now('UTC');
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(),
            'branch_id' => null,
            'actor_user_id' => $actor->getKey(),
            'actor_type' => 'user',
            'action' => $action,
            'subject_type' => 'custom_role',
            'subject_id' => (string) $role->getKey(),
            'outcome' => 'success',
            'reason_code' => 'role_management',
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => (string) request()->attributes->get('request_id', Str::uuid()),
            'occurred_at' => $now,
        ]);
    }
}
