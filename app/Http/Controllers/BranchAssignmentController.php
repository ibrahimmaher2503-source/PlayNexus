<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

class BranchAssignmentController extends Controller
{
    private const ROLES = ['branch_manager', 'reception_staff', 'cashier'];

    private const MANAGED_STAFF_ROLES = ['reception_staff', 'reception', 'cashier'];

    private const SEARCH_MAX_LENGTH = 100;

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->staffContext($request);
        $isOwner = $this->isOwner($actor, $tenant);
        $search = $this->normalizeSearch($request->query('q'));
        $staff = $this->staffQuery($tenant, $search, $actor, $isOwner)->paginate(25)->withQueryString();
        if ($request->query('q') !== null) {
            $staff->appends(['q' => $search]);
        }
        $selectedUser = $this->selectedUser($request, $tenant, $actor, $isOwner);
        $branches = collect();
        $assignments = collect();
        $customRoles = $isOwner
            ? CustomRole::query()
                ->where('tenant_id', $tenant->id)
                ->whereHas('permissions', fn ($query) => $query->where('permission', 'branches.view'))
                ->orderBy('name')
                ->pluck('name', 'code')
            : collect();

        if ($selectedUser) {
            $branches = $this->manageableBranches($actor, $tenant, $isOwner)->get(['id', 'name', 'is_active']);
            $assignments = DB::table('branch_user')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $selectedUser->id)
                ->whereIn('branch_id', $branches->modelKeys())
                ->get(['branch_id', 'role', 'is_active'])
                ->keyBy('branch_id');
        }

        return view('assignments.index', compact('actor', 'tenant', 'staff', 'selectedUser', 'branches', 'assignments', 'search', 'customRoles', 'isOwner'));
    }

    public function update(Request $request, User $user, Branch $branch): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->staffContext($request);
        $isOwner = $this->isOwner($actor, $tenant);
        [$target, $scopedBranch] = $this->scopedTarget($user, $branch, $tenant, $actor, $isOwner);

        if ($target->status !== 'active') {
            throw new HttpException(409, __('assignments.conflict'));
        }

        if (! $isOwner) {
            $this->authorizeManagerTarget($actor, $tenant, $target);

            if ($request->has('role') && ! in_array($request->input('role'), self::MANAGED_STAFF_ROLES, true)) {
                abort(403);
            }
        }

        $validator = Validator::make(
            $request->all(),
            [
                'role' => ['required', 'string', 'max:50', Rule::in($this->allowedRoles($tenant, $isOwner))],
                'is_active' => ['required', 'boolean'],
                'expected_role' => ['present', 'nullable', 'string', 'max:50'],
                'expected_is_active' => ['present', 'nullable', 'boolean'],
            ],
            [
                'role.required' => __('assignments.errors.role_required'),
                'role.string' => __('assignments.errors.role_invalid'),
                'role.max' => __('assignments.errors.role_invalid'),
                'role.in' => __('assignments.errors.role_invalid'),
                'is_active.required' => __('assignments.errors.active_required'),
                'is_active.boolean' => __('assignments.errors.active_invalid'),
                'expected_role.present' => __('assignments.errors.expected_role_required'),
                'expected_role.string' => __('assignments.errors.expected_role_invalid'),
                'expected_role.max' => __('assignments.errors.expected_role_invalid'),
                'expected_is_active.present' => __('assignments.errors.expected_active_required'),
                'expected_is_active.boolean' => __('assignments.errors.expected_active_invalid'),
            ],
        );

        if ($validator->fails()) {
            return $this->validationResponse($request, $validator);
        }

        $data = $validator->validated();
        $desiredRole = $data['role'];
        $desiredActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $expectedRole = $data['expected_role'];
        $expectedActive = $data['expected_is_active'] === null
            ? null
            : filter_var($data['expected_is_active'], FILTER_VALIDATE_BOOLEAN);

        $result = DB::transaction(function () use ($actor, $tenant, $target, $scopedBranch, $desiredRole, $desiredActive, $expectedRole, $expectedActive, $isOwner): array {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $freshActor = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($freshActor)->authorize('manageStaff', $lockedTenant);

            $lockedTarget = User::query()
                ->whereKey($target->id)
                ->where('tenant_id', $lockedTenant->id)
                ->lockForUpdate()
                ->first();
            abort_unless($lockedTarget, 404);

            if (DB::table('tenant_owners')
                ->where('tenant_id', $lockedTenant->id)
                ->where('user_id', $lockedTarget->id)
                ->exists()) {
                abort(403);
            }

            if ($lockedTarget->status !== 'active') {
                throw new HttpException(409, __('assignments.conflict'));
            }

            $lockedBranch = Branch::query()
                ->whereKey($scopedBranch->id)
                ->where('tenant_id', $lockedTenant->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            abort_unless($lockedBranch, 404);

            if (! $isOwner) {
                Gate::forUser($freshActor)->authorize('manageStaff', $lockedBranch);
            }

            $pivot = DB::table('branch_user')
                ->where('tenant_id', $lockedTenant->id)
                ->where('user_id', $lockedTarget->id)
                ->where('branch_id', $lockedBranch->id)
                ->lockForUpdate()
                ->first();

            if (! $isOwner && $pivot && ! in_array($pivot->role, self::MANAGED_STAFF_ROLES, true)) {
                abort(403);
            }

            $hasExpectedState = $pivot !== null
                ? $expectedRole !== null
                    && $expectedActive !== null
                    && (string) $pivot->role === $expectedRole
                    && (bool) $pivot->is_active === $expectedActive
                : $expectedRole === null && $expectedActive === null;

            if (! $hasExpectedState) {
                throw new HttpException(409, __('assignments.conflict'));
            }

            $after = [
                'branch_id' => (int) $lockedBranch->id,
                'role' => $desiredRole,
                'is_active' => $desiredActive,
            ];
            $before = $pivot ? [
                'branch_id' => (int) $pivot->branch_id,
                'role' => $pivot->role,
                'is_active' => (bool) $pivot->is_active,
            ] : null;

            if ($pivot && (string) $pivot->role === $desiredRole && (bool) $pivot->is_active === $desiredActive) {
                return ['conflict' => false, 'changed' => false];
            }

            $now = now('UTC');
            if ($pivot) {
                DB::table('branch_user')
                    ->where('tenant_id', $lockedTenant->id)
                    ->where('user_id', $lockedTarget->id)
                    ->where('branch_id', $lockedBranch->id)
                    ->update([
                        'role' => $desiredRole,
                        'is_active' => $desiredActive,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('branch_user')->insert([
                    'tenant_id' => $lockedTenant->id,
                    'branch_id' => $lockedBranch->id,
                    'user_id' => $lockedTarget->id,
                    'role' => $desiredRole,
                    'is_active' => $desiredActive,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->id,
                'branch_id' => $lockedBranch->id,
                'actor_user_id' => $freshActor->id,
                'actor_type' => 'user',
                'action' => 'staff.branch_assignment.changed',
                'subject_type' => 'user',
                'subject_id' => (string) $lockedTarget->id,
                'outcome' => 'success',
                'reason_code' => 'access_review',
                'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) request()->attributes->get('request_id', Str::uuid()),
                'occurred_at' => $now,
            ]);

            return ['conflict' => false, 'changed' => true];
        });

        $message = $result['changed']
            ? __('assignments.updated')
            : __('assignments.no_change');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'changed' => $result['changed']]);
        }

        return redirect()
            ->route('assignments.index', ['user_id' => $target->id])
            ->with('status', $message);
    }

    private function staffContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();

        Gate::forUser($actor)->authorize('manageStaff', $tenant);

        return [$actor, $tenant];
    }

    private function isOwner(User $actor, Tenant $tenant): bool
    {
        return DB::table('tenant_owners')
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $actor->getKey())
            ->exists();
    }

    private function staffQuery(Tenant $tenant, string $search = '', ?User $actor = null, bool $isOwner = true)
    {
        $query = User::query()
            ->where('users.tenant_id', $tenant->id)
            ->whereNotExists(function ($query) use ($tenant): void {
                $query->selectRaw('1')
                    ->from('tenant_owners')
                    ->whereColumn('tenant_owners.user_id', 'users.id')
                    ->where('tenant_owners.tenant_id', $tenant->id);
            })
            ->select(['users.id', 'users.name', 'users.email', 'users.status'])
            ->orderBy('users.id');

        if (! $isOwner && $actor) {
            $query->whereExists(function ($query) use ($actor, $tenant): void {
                $query->selectRaw('1')
                    ->from('branch_user as target_assignment')
                    ->join('branch_user as manager_assignment', function ($join) use ($actor, $tenant): void {
                        $join->on('manager_assignment.branch_id', '=', 'target_assignment.branch_id')
                            ->where('manager_assignment.tenant_id', $tenant->id)
                            ->where('manager_assignment.user_id', $actor->id)
                            ->where('manager_assignment.role', 'branch_manager')
                            ->where('manager_assignment.is_active', true);
                    })
                    ->join('branches as managed_branch', function ($join) use ($tenant): void {
                        $join->on('managed_branch.id', '=', 'target_assignment.branch_id')
                            ->where('managed_branch.tenant_id', $tenant->id)
                            ->where('managed_branch.is_active', true);
                    })
                    ->whereColumn('target_assignment.user_id', 'users.id')
                    ->where('target_assignment.tenant_id', $tenant->id)
                    ->whereIn('target_assignment.role', self::MANAGED_STAFF_ROLES);
            })->whereNotExists(function ($query) use ($tenant): void {
                $query->selectRaw('1')
                    ->from('branch_user as manager_target')
                    ->whereColumn('manager_target.user_id', 'users.id')
                    ->where('manager_target.tenant_id', $tenant->id)
                    ->where('manager_target.role', 'branch_manager')
                    ->where('manager_target.is_active', true);
            });
        }

        if ($search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $escape = DB::connection()->getDriverName() === 'mysql' ? '\\\\' : '\\';

            $query->where(function ($query) use ($pattern, $escape): void {
                $query->whereRaw("users.name LIKE ? ESCAPE '{$escape}'", [$pattern])
                    ->orWhereRaw("users.email LIKE ? ESCAPE '{$escape}'", [$pattern]);
            });
        }

        return $query;
    }

    private function normalizeSearch(mixed $value): string
    {
        return is_string($value) ? Str::substr(trim($value), 0, self::SEARCH_MAX_LENGTH) : '';
    }

    /** @return list<string> */
    private function allowedRoles(Tenant $tenant, bool $isOwner = true): array
    {
        if (! $isOwner) {
            return array_values(array_diff(self::ROLES, ['branch_manager']));
        }

        return array_merge(self::ROLES, CustomRole::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('permissions', fn ($query) => $query->where('permission', 'branches.view'))
            ->pluck('code')
            ->all());
    }

    private function selectedUser(Request $request, Tenant $tenant, User $actor, bool $isOwner): ?User
    {
        if ($request->query('user_id') === null || $request->query('user_id') === '') {
            return null;
        }

        $selectedId = filter_var($request->query('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless($selectedId, 404);

        return $this->staffQuery($tenant, '', $actor, $isOwner)->whereKey($selectedId)->firstOrFail();
    }

    private function scopedTarget(User $user, Branch $branch, Tenant $tenant, User $actor, bool $isOwner): array
    {
        $target = User::query()
            ->whereKey($user->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        if (DB::table('tenant_owners')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->exists()) {
            abort(403);
        }

        $scopedBranch = Branch::query()
            ->whereKey($branch->id)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $isOwner) {
            Gate::forUser($actor)->authorize('manageStaff', $scopedBranch);
        }

        return [$target, $scopedBranch];
    }

    private function manageableBranches(User $actor, Tenant $tenant, bool $isOwner)
    {
        $query = Branch::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name');

        if (! $isOwner) {
            $query->whereExists(function ($query) use ($actor, $tenant): void {
                $query->selectRaw('1')
                    ->from('branch_user')
                    ->whereColumn('branch_user.branch_id', 'branches.id')
                    ->where('branch_user.tenant_id', $tenant->id)
                    ->where('branch_user.user_id', $actor->id)
                    ->where('branch_user.role', 'branch_manager')
                    ->where('branch_user.is_active', true);
            });
        }

        return $query;
    }

    private function authorizeManagerTarget(User $actor, Tenant $tenant, User $target): void
    {
        $managedAssignments = DB::table('branch_user as target_assignment')
            ->join('branch_user as manager_assignment', function ($join) use ($actor, $tenant): void {
                $join->on('manager_assignment.branch_id', '=', 'target_assignment.branch_id')
                    ->where('manager_assignment.tenant_id', $tenant->id)
                    ->where('manager_assignment.user_id', $actor->id)
                    ->where('manager_assignment.role', 'branch_manager')
                    ->where('manager_assignment.is_active', true);
            })
            ->join('branches as managed_branch', function ($join) use ($tenant): void {
                $join->on('managed_branch.id', '=', 'target_assignment.branch_id')
                    ->where('managed_branch.tenant_id', $tenant->id)
                    ->where('managed_branch.is_active', true);
            })
            ->where('target_assignment.tenant_id', $tenant->id)
            ->where('target_assignment.user_id', $target->id)
            ->get(['target_assignment.role']);

        if ($managedAssignments->isEmpty()) {
            abort(404);
        }

        if ($managedAssignments->contains(fn (object $assignment): bool => ! in_array($assignment->role, self::MANAGED_STAFF_ROLES, true))
            || DB::table('branch_user')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $target->id)
                ->where('role', 'branch_manager')
                ->where('is_active', true)
                ->exists()) {
            abort(403);
        }
    }

    private function validationResponse(Request $request, $validator): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('assignments.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        return back()->withErrors($validator)->withInput();
    }
}
