<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BranchReadiness;
use App\Support\SubscriptionAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BranchAdminController extends Controller
{
    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->tenantContext($request);
        $canCreate = Gate::forUser($actor)->allows('view', $tenant);

        $branches = Branch::query()
            ->select(['id', 'name', 'is_active'])
            ->where('tenant_id', $tenant->getKey())
            ->withCount(['playSessions as active_sessions_count' => fn ($query) => $query->where('tenant_id', $tenant->getKey())->where('status', 'active')])
            ->when(! $canCreate, function ($query) use ($actor, $tenant): void {
                $query->whereExists(function ($assignment) use ($actor, $tenant): void {
                    $assignment->selectRaw('1')
                        ->from('branch_user')
                        ->whereColumn('branch_user.branch_id', 'branches.id')
                        ->where('branch_user.tenant_id', $tenant->getKey())
                        ->where('branch_user.user_id', $actor->getKey())
                        ->where('branch_user.role', 'branch_manager')
                        ->where('branch_user.is_active', true);
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if (! $canCreate && $branches->isEmpty()) {
            abort(403);
        }

        return view('branches.manage', compact('tenant', 'branches', 'canCreate'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->ownerContext($request);
        $rawName = $request->input('name');
        $name = is_string($rawName) ? trim($rawName) : $rawName;
        $validator = Validator::make(
            ['name' => $name, 'creation_key' => $request->header('Idempotency-Key', $request->input('creation_key'))],
            [
                'name' => ['required', 'string', 'min:2', 'max:120'],
                'creation_key' => ['nullable', 'uuid'],
            ],
            [
                'name.required' => __('branches.validation.name_required'),
                'name.string' => __('branches.validation.name_invalid'),
                'name.min' => __('branches.validation.name_invalid'),
                'name.max' => __('branches.validation.name_invalid'),
            ],
        );

        if ($validator->fails()) {
            return $this->validationResponse($request, $validator);
        }

        $data = $validator->validated();
        $name = $data['name'];
        $creationKey = $data['creation_key'] ?? (string) Str::uuid();

        [$branch, $created] = DB::transaction(function () use ($actor, $tenant, $name, $creationKey): array {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

            // The tenant row is the serialization point for branch capacity.
            // A route guard is useful UX, but cannot make this check safe when
            // two requests race to create the final permitted branch.
            $subscription = SubscriptionAccess::forTenant($lockedTenant);
            if (! $subscription->allows('write', true)) {
                throw new HttpException(402, 'This subscription is read-only. New branches are unavailable.');
            }

            $existing = Branch::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('creation_key', $creationKey)
                ->first();

            if ($existing) {
                if ($existing->name !== $name) {
                    throw new HttpException(409, __('branches.conflict'));
                }

                return [$existing, false];
            }

            $branchCount = Branch::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->count();

            if (! $subscription->canCreate('branches', $branchCount)) {
                throw new HttpException(402, 'The effective branch limit has been reached.');
            }

            $branch = new Branch([
                'tenant_id' => $lockedTenant->getKey(),
                'creation_key' => $creationKey,
                'name' => $name,
                'payment_methods' => ['cash'],
                // Creation produces a safe draft. Operational activation is a
                // separate gate after authoritative settings are complete.
                'is_active' => false,
            ]);
            $branch->save();

            $now = now('UTC');
            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $branch->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'branch.created',
                'subject_type' => 'branch',
                'subject_id' => (string) $branch->getKey(),
                'outcome' => 'success',
                'reason_code' => 'setup_change',
                'before_json' => null,
                'after_json' => json_encode(['name' => $branch->name], JSON_THROW_ON_ERROR),
                'request_id' => (string) request()->attributes->get('request_id', Str::uuid()),
                'occurred_at' => $now,
            ]);

            return [$branch, true];
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('branches.created'), 'branch_id' => $branch->getKey(), 'created' => $created], $created ? 201 : 200);
        }

        return to_route('branches.manage')->with($created ? 'success' : 'status_message', __('branches.created'));
    }

    public function updateStatus(Request $request, Branch $branch): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->tenantContext($request);
        $target = Branch::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($branch->getKey())
            ->firstOrFail();
        Gate::forUser($actor)->authorize('changeStatus', $target);

        $validator = Validator::make(
            $request->all(),
            [
                'is_active' => ['required', 'boolean'],
                'expected_is_active' => ['required', 'boolean'],
            ],
            [
                'is_active.required' => __('branches.validation.status_required'),
                'is_active.boolean' => __('branches.validation.status_invalid'),
                'expected_is_active.required' => __('branches.validation.expected_status_required'),
                'expected_is_active.boolean' => __('branches.validation.expected_status_invalid'),
            ],
        );

        if ($validator->fails()) {
            return $this->validationResponse($request, $validator);
        }

        $data = $validator->validated();
        $desiredActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $expectedActive = filter_var($data['expected_is_active'], FILTER_VALIDATE_BOOLEAN);

        $changed = DB::transaction(function () use ($actor, $tenant, $target, $desiredActive, $expectedActive): bool {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $lockedTarget = Branch::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();
            abort_unless($lockedTarget, 404);
            Gate::forUser($lockedActor)->authorize('changeStatus', $lockedTarget);

            if ((bool) $lockedTarget->is_active !== $expectedActive) {
                throw new HttpException(409, __('branches.conflict'));
            }

            if ((bool) $lockedTarget->is_active === $desiredActive) {
                return false;
            }

            if ($desiredActive) {
                $this->assertActivationReady($lockedTarget);
            }

            $before = ['is_active' => (bool) $lockedTarget->is_active];
            $after = ['is_active' => $desiredActive];
            $now = now('UTC');

            DB::table('branches')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('id', $lockedTarget->getKey())
                ->update(['is_active' => $desiredActive, 'updated_at' => $now]);

            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $lockedTarget->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'branch.status.changed',
                'subject_type' => 'branch',
                'subject_id' => (string) $lockedTarget->getKey(),
                'outcome' => 'success',
                'reason_code' => 'access_review',
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) request()->attributes->get('request_id', Str::uuid()),
                'occurred_at' => $now,
            ]);

            return true;
        });

        $message = $changed ? __('branches.status_updated') : __('branches.no_change');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'changed' => $changed]);
        }

        return to_route('branches.manage')->with($changed ? 'success' : 'status_message', $message);
    }

    /** @return array{User, Tenant} */
    private function tenantContext(Request $request): array
    {
        $actor = User::query()->whereKey($request->user()->getAuthIdentifier())->where('status', 'active')->firstOrFail();
        $tenant = Tenant::query()
            ->whereKey($actor->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        return [$actor, $tenant];
    }

    /** @return array{User, Tenant} */
    private function ownerContext(Request $request): array
    {
        [$actor, $tenant] = $this->tenantContext($request);
        Gate::forUser($actor)->authorize('view', $tenant);

        return [$actor, $tenant];
    }

    private function validationResponse(Request $request, $validator): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('branches.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        return back()->withErrors($validator)->withInput();
    }

    private function assertActivationReady(Branch $branch): void
    {
        $hours = DB::table('branch_opening_hours')
            ->where('tenant_id', $branch->tenant_id)
            ->where('branch_id', $branch->getKey())
            ->get(['weekday', 'opens_at', 'closes_at', 'is_closed']);

        if (! BranchReadiness::isActivationReady($branch, $hours)) {
            throw ValidationException::withMessages([
                'is_active' => __('branches.validation.activation_not_ready'),
            ]);
        }
    }
}
