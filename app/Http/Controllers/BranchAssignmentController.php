<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

class BranchAssignmentController extends Controller
{
    private const ROLES = ['branch_manager', 'reception_staff', 'cashier'];

    private const REASONS = ['staffing_change', 'access_review', 'correction'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->ownerContext($request);
        $staff = $this->staffQuery($tenant)->paginate(25)->withQueryString();
        $selectedUser = $this->selectedUser($request, $tenant);
        $branches = collect();
        $assignments = collect();

        if ($selectedUser) {
            $branches = Branch::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']);
            $assignments = DB::table('branch_user')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $selectedUser->id)
                ->whereIn('branch_id', $branches->modelKeys())
                ->get(['branch_id', 'role', 'is_active'])
                ->keyBy('branch_id');
        }

        return view('assignments.index', compact('actor', 'tenant', 'staff', 'selectedUser', 'branches', 'assignments'));
    }

    public function update(Request $request, User $user, Branch $branch): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->ownerContext($request);
        [$target, $scopedBranch] = $this->scopedTarget($user, $branch, $tenant);

        if ($target->status !== 'active') {
            throw new HttpException(409, __('assignments.conflict'));
        }

        $validator = Validator::make(
            $request->all(),
            [
                'role' => ['required', 'string', 'max:50', 'in:'.implode(',', self::ROLES)],
                'is_active' => ['required', 'boolean'],
                'reason_code' => ['required', 'string', 'in:'.implode(',', self::REASONS)],
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
                'reason_code.required' => __('assignments.errors.reason_required'),
                'reason_code.string' => __('assignments.errors.reason_invalid'),
                'reason_code.in' => __('assignments.errors.reason_invalid'),
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

        $result = DB::transaction(function () use ($actor, $tenant, $target, $scopedBranch, $desiredRole, $desiredActive, $expectedRole, $expectedActive, $data): array {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $freshActor = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($freshActor)->authorize('view', $lockedTenant);

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

            $pivot = DB::table('branch_user')
                ->where('tenant_id', $lockedTenant->id)
                ->where('user_id', $lockedTarget->id)
                ->where('branch_id', $lockedBranch->id)
                ->lockForUpdate()
                ->first();

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
                'reason_code' => $data['reason_code'],
                'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) Str::uuid(),
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

    private function ownerContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();

        Gate::forUser($actor)->authorize('view', $tenant);

        return [$actor, $tenant];
    }

    private function staffQuery(Tenant $tenant)
    {
        return User::query()
            ->where('users.tenant_id', $tenant->id)
            ->whereNotExists(function ($query) use ($tenant): void {
                $query->selectRaw('1')
                    ->from('tenant_owners')
                    ->whereColumn('tenant_owners.user_id', 'users.id')
                    ->where('tenant_owners.tenant_id', $tenant->id);
            })
            ->select(['users.id', 'users.name', 'users.email', 'users.status'])
            ->orderBy('users.id');
    }

    private function selectedUser(Request $request, Tenant $tenant): ?User
    {
        if ($request->query('user_id') === null || $request->query('user_id') === '') {
            return null;
        }

        $selectedId = filter_var($request->query('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless($selectedId, 404);

        return $this->staffQuery($tenant)->whereKey($selectedId)->firstOrFail();
    }

    private function scopedTarget(User $user, Branch $branch, Tenant $tenant): array
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

        return [$target, $scopedBranch];
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
