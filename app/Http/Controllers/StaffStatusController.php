<?php

namespace App\Http\Controllers;

use App\Models\CustomRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SubscriptionAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StaffStatusController extends Controller
{
    private const MANAGED_STAFF_ROLES = ['reception_staff', 'reception', 'cashier'];

    private const STATUS_VALUES = ['invited', 'active', 'suspended', 'disabled'];

    private const MUTABLE_STATUS_VALUES = ['active', 'suspended', 'disabled'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->staffContext($request);
        $isOwner = $this->isOwner($actor, $tenant);
        $search = $this->normalizeSearch($request->query('q'));

        $staff = User::query()
            ->where('users.tenant_id', $tenant->id)
            ->select(['users.id', 'users.tenant_id', 'users.name', 'users.email', 'users.status'])
            ->selectSub(
                DB::table('tenant_owners')
                    ->selectRaw('1')
                    ->whereColumn('tenant_owners.user_id', 'users.id')
                    ->where('tenant_owners.tenant_id', $tenant->id)
                    ->limit(1),
                'is_owner',
            )
            ->selectSub($isOwner
                ? DB::table('branch_user')
                    ->join('branches', function ($join) use ($tenant): void {
                        $join->on('branches.id', '=', 'branch_user.branch_id')
                            ->where('branches.tenant_id', $tenant->id)
                            ->where('branches.is_active', true);
                    })
                    ->selectRaw('COUNT(DISTINCT branch_user.branch_id)')
                    ->whereColumn('branch_user.user_id', 'users.id')
                    ->where('branch_user.tenant_id', $tenant->id)
                    ->where('branch_user.is_active', true)
                : DB::table('branch_user as target_assignment')
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
                    ->selectRaw('COUNT(DISTINCT target_assignment.branch_id)')
                    ->whereColumn('target_assignment.user_id', 'users.id')
                    ->where('target_assignment.tenant_id', $tenant->id)
                    ->where('target_assignment.is_active', true)
                    ->whereIn('target_assignment.role', self::MANAGED_STAFF_ROLES),
                'branches_count')
            ->selectSub($isOwner
                ? DB::table('branch_user')
                    ->select('branch_user.role')
                    ->whereColumn('branch_user.user_id', 'users.id')
                    ->where('branch_user.tenant_id', $tenant->id)
                    ->where('branch_user.is_active', true)
                    ->orderBy('branch_user.branch_id')
                    ->limit(1)
                : DB::table('branch_user as target_assignment')
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
                    ->select('target_assignment.role')
                    ->whereColumn('target_assignment.user_id', 'users.id')
                    ->where('target_assignment.tenant_id', $tenant->id)
                    ->where('target_assignment.is_active', true)
                    ->whereIn('target_assignment.role', self::MANAGED_STAFF_ROLES)
                    ->orderBy('target_assignment.branch_id')
                    ->limit(1),
                'role_code')
            ->orderBy('users.id')
            ->when(! $isOwner, function ($query) use ($actor, $tenant): void {
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
            })
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $escape = DB::connection()->getDriverName() === 'mysql' ? '\\\\' : '\\';

                $query->where(function ($query) use ($pattern, $escape): void {
                    $query->whereRaw("users.name LIKE ? ESCAPE '{$escape}'", [$pattern])
                        ->orWhereRaw("users.email LIKE ? ESCAPE '{$escape}'", [$pattern]);
                });
            })
            ->paginate(25)
            ->withQueryString();

        $roleLabels = is_array(__('assignments.roles')) ? __('assignments.roles') : [];
        if ($isOwner) {
            $roleLabels = array_merge(
                $roleLabels,
                CustomRole::query()->where('tenant_id', $tenant->id)->pluck('name', 'code')->all(),
            );
        }

        return view('staff.index', compact('actor', 'tenant', 'staff', 'search', 'roleLabels', 'isOwner'));
    }

    public function create(Request $request): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);

        return view('staff.create', compact('actor', 'tenant'));
    }

    public function edit(Request $request, User $user): View
    {
        [, $tenant] = $this->authorizedContext($request);
        $member = User::query()->where('tenant_id', $tenant->id)->whereKey($user->id)->firstOrFail();
        $this->denyOwnerTarget($tenant, $member);

        return view('staff.edit', compact('tenant', 'member'));
    }

    public function updateIdentity(Request $request, User $user): RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $member = User::query()->where('tenant_id', $tenant->id)->whereKey($user->id)->firstOrFail();
        $this->denyOwnerTarget($tenant, $member);
        $request->merge([
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
            'email' => is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : $request->input('email'),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'expected_name' => ['required', 'string', 'max:255'],
            'expected_email' => ['required', 'string', 'max:255'],
        ]);
        try {
            DB::transaction(function () use ($actor, $tenant, $member, $data): void {
                $lockedTenant = Tenant::query()->whereKey($tenant->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
                $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($lockedActor)->authorize('view', $lockedTenant);
                $target = User::query()->where('tenant_id', $tenant->id)->whereKey($member->id)->lockForUpdate()->firstOrFail();
                $this->denyOwnerTarget($lockedTenant, $target);
                if ($target->name !== $data['expected_name'] || $target->email !== $data['expected_email']) {
                    throw new HttpException(409, __('staff.conflict'));
                }
                if ($target->name === $data['name'] && $target->email === $data['email']) {
                    return;
                }
                $before = ['name' => $target->name, 'email_hash' => hash('sha256', $target->email)];
                if ($target->email !== $data['email']) {
                    DB::table('password_reset_tokens')->where('email', $target->email)->delete();
                    $target->auth_version = (int) $target->auth_version + 1;
                    $target->remember_token = Str::random(60);
                    $target->email_verified_at = null;
                }
                $target->name = $data['name'];
                $target->email = $data['email'];
                $target->save();
                DB::table('audit_logs')->insert([
                    'tenant_id' => $tenant->id, 'branch_id' => null,
                    'actor_user_id' => $actor->id, 'actor_type' => 'user',
                    'action' => 'staff.identity.updated', 'subject_type' => 'user',
                    'subject_id' => (string) $target->id, 'outcome' => 'success',
                    'reason_code' => 'staffing_change',
                    'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after_json' => json_encode(['name' => $target->name, 'email_hash' => hash('sha256', $target->email)], JSON_THROW_ON_ERROR),
                    'request_id' => (string) request()->attributes->get('request_id', Str::uuid()), 'occurred_at' => now('UTC'),
                ]);
            });

        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('staff.validation.email_taken')]);
        }

        return to_route('staff.index')->with('success', __('staff.identity_updated'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);

        $name = $request->input('name');
        $email = $request->input('email');
        $request->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
        ], [
            'name.required' => __('staff.validation.name_required'),
            'name.string' => __('staff.validation.name_invalid'),
            'name.max' => __('staff.validation.name_invalid'),
            'email.required' => __('staff.validation.email_required'),
            'email.string' => __('staff.validation.email_invalid'),
            'email.email' => __('staff.validation.email_invalid'),
            'email.max' => __('staff.validation.email_invalid'),
            'email.unique' => __('staff.validation.email_taken'),
        ]);

        try {
            DB::transaction(function () use ($actor, $tenant, $validated): void {
                $lockedTenant = Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
                Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

                // Locking the tenant makes the limit decision and user insert
                // one critical section, so concurrent requests cannot both
                // consume the last available staff seat.
                $subscription = SubscriptionAccess::forTenant($lockedTenant);
                if (! $subscription->allows('write', true)) {
                    throw new HttpException(402, 'This subscription is read-only. New staff are unavailable.');
                }

                $staffCount = User::query()
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->count();

                if (! $subscription->canCreate('users', $staffCount)) {
                    throw new HttpException(402, 'The effective staff limit has been reached.');
                }

                $created = new User;
                $created->tenant_id = $lockedTenant->getKey();
                $created->name = $validated['name'];
                $created->email = $validated['email'];
                $created->email_verified_at = null;
                $created->password = Hash::make(Str::random(64));
                $created->status = 'active';
                $created->save();

                $now = now('UTC');
                DB::table('audit_logs')->insert([
                    'tenant_id' => $lockedTenant->getKey(),
                    'branch_id' => null,
                    'actor_user_id' => $lockedActor->getKey(),
                    'actor_type' => 'user',
                    'action' => 'staff.created',
                    'subject_type' => 'user',
                    'subject_id' => (string) $created->getKey(),
                    'outcome' => 'success',
                    'reason_code' => 'staffing_change',
                    'before_json' => null,
                    'after_json' => json_encode(['status' => 'active'], JSON_THROW_ON_ERROR),
                    'request_id' => (string) request()->attributes->get('request_id', Str::uuid()),
                    'occurred_at' => $now,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'message' => __('staff.validation_failed'),
                    'errors' => ['email' => [__('staff.validation.email_taken')]],
                ], 409);
            }

            return back()->withErrors(['email' => __('staff.validation.email_taken')])->withInput();
        }

        return to_route('staff.index')->with('success', __('staff.created'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        [$actor, $tenant] = $this->staffContext($request);
        $isOwner = $this->isOwner($actor, $tenant);

        $target = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($user->getKey())
            ->firstOrFail();

        $this->denyOwnerTarget($tenant, $target);
        if (! $isOwner) {
            $this->authorizeManagerTarget($actor, $tenant, $target);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::MUTABLE_STATUS_VALUES)],
            'expected_status' => ['required', 'string', 'in:'.implode(',', self::STATUS_VALUES)],
        ], [
            'status.required' => __('staff.validation.status_required'),
            'status.in' => __('staff.validation.status_invalid'),
            'expected_status.required' => __('staff.validation.expected_status_required'),
            'expected_status.in' => __('staff.validation.expected_status_invalid'),
        ]);

        $changed = DB::transaction(function () use ($actor, $tenant, $target, $validated, $isOwner): bool {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('manageStaff', $lockedTenant);

            $lockedTarget = User::query()
                ->where('tenant_id', $lockedTenant->id)
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->denyOwnerTarget($lockedTenant, $lockedTarget);
            if (! $isOwner) {
                $this->authorizeManagerTarget($lockedActor, $lockedTenant, $lockedTarget);
            }

            if ($lockedTarget->status !== $validated['expected_status']) {
                throw new HttpException(409, __('staff.conflict'));
            }

            if ($lockedTarget->status === $validated['status']) {
                return false;
            }

            $before = ['status' => $lockedTarget->status];
            $after = ['status' => $validated['status']];

            DB::table('users')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('id', $lockedTarget->getKey())
                ->update([
                    'status' => $validated['status'],
                    'auth_version' => DB::raw('auth_version + 1'),
                    'updated_at' => now('UTC'),
                ]);

            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => null,
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'staff.status.changed',
                'subject_type' => 'user',
                'subject_id' => (string) $lockedTarget->getKey(),
                'outcome' => 'success',
                'reason_code' => 'staffing_change',
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) request()->attributes->get('request_id', Str::uuid()),
                'occurred_at' => now('UTC'),
            ]);

            return true;
        });

        return to_route('staff.index')->with(
            $changed ? 'success' : 'status_message',
            __($changed ? 'staff.updated' : 'staff.no_change'),
        );
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->firstOrFail();

        Gate::forUser($actor)->authorize('view', $tenant);

        return [$actor, $tenant];
    }

    /** @return array{User, Tenant} */
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

    private function authorizeManagerTarget(User $actor, Tenant $tenant, User $target): void
    {
        $managedAssignments = DB::table('branch_user as target_assignment')
            ->join('branch_user as manager_assignment', function ($join) use ($actor, $tenant): void {
                $join->on('manager_assignment.branch_id', '=', 'target_assignment.branch_id')
                    ->where('manager_assignment.tenant_id', $tenant->getKey())
                    ->where('manager_assignment.user_id', $actor->getKey())
                    ->where('manager_assignment.role', 'branch_manager')
                    ->where('manager_assignment.is_active', true);
            })
            ->join('branches as managed_branch', function ($join) use ($tenant): void {
                $join->on('managed_branch.id', '=', 'target_assignment.branch_id')
                    ->where('managed_branch.tenant_id', $tenant->getKey())
                    ->where('managed_branch.is_active', true);
            })
            ->where('target_assignment.tenant_id', $tenant->getKey())
            ->where('target_assignment.user_id', $target->getKey())
            ->get(['target_assignment.role']);

        if ($managedAssignments->isEmpty()) {
            abort(404);
        }

        if ($managedAssignments->contains(fn (object $assignment): bool => ! in_array($assignment->role, self::MANAGED_STAFF_ROLES, true))
            || DB::table('branch_user')
                ->where('tenant_id', $tenant->getKey())
                ->where('user_id', $target->getKey())
                ->where('role', 'branch_manager')
                ->where('is_active', true)
                ->exists()) {
            abort(403);
        }

        // Account status is tenant-wide. A branch manager must not revoke
        // staff access in another manager's branch via this global command.
        $outsideScope = DB::table('branch_user as target_scope')
            ->where('target_scope.tenant_id', $tenant->getKey())
            ->where('target_scope.user_id', $target->getKey())
            ->where('target_scope.is_active', true)
            ->whereNotExists(function ($query) use ($actor, $tenant): void {
                $query->selectRaw('1')->from('branch_user as actor_scope')
                    ->whereColumn('actor_scope.branch_id', 'target_scope.branch_id')
                    ->where('actor_scope.tenant_id', $tenant->getKey())
                    ->where('actor_scope.user_id', $actor->getKey())
                    ->where('actor_scope.role', 'branch_manager')
                    ->where('actor_scope.is_active', true);
            })->exists();
        abort_if($outsideScope, 403);
    }

    private function denyOwnerTarget(Tenant $tenant, User $target): void
    {
        abort_if(
            DB::table('tenant_owners')
                ->where('tenant_id', $tenant->getKey())
                ->where('user_id', $target->getKey())
                ->exists(),
            403,
        );
    }

    private function normalizeSearch(mixed $value): string
    {
        return is_string($value) ? Str::substr(trim($value), 0, 100) : '';
    }
}
