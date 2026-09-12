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

class BranchAdminController extends Controller
{
    private const REASON_CODES = ['setup_change', 'access_review', 'correction'];

    public function index(Request $request): View
    {
        [, $tenant] = $this->authorizedContext($request);

        $branches = Branch::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'is_active']);

        return view('branches.manage', compact('tenant', 'branches'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $rawName = $request->input('name');
        $name = is_string($rawName) ? trim($rawName) : $rawName;
        $validator = Validator::make(
            ['name' => $name],
            ['name' => ['required', 'string', 'min:2', 'max:120']],
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

        $name = $validator->validated()['name'];

        $branch = DB::transaction(function () use ($actor, $tenant, $name): Branch {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

            $branch = new Branch([
                'tenant_id' => $lockedTenant->getKey(),
                'name' => $name,
                'is_active' => true,
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
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
            ]);

            return $branch;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('branches.created'), 'branch_id' => $branch->getKey()], 201);
        }

        return to_route('branches.manage')->with('success', __('branches.created'));
    }

    public function updateStatus(Request $request, Branch $branch): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $target = Branch::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($branch->getKey())
            ->firstOrFail();

        $validator = Validator::make(
            $request->all(),
            [
                'is_active' => ['required', 'boolean'],
                'expected_is_active' => ['required', 'boolean'],
                'reason_code' => ['required', 'string', 'in:'.implode(',', self::REASON_CODES)],
            ],
            [
                'is_active.required' => __('branches.validation.status_required'),
                'is_active.boolean' => __('branches.validation.status_invalid'),
                'expected_is_active.required' => __('branches.validation.expected_status_required'),
                'expected_is_active.boolean' => __('branches.validation.expected_status_invalid'),
                'reason_code.required' => __('branches.validation.reason_required'),
                'reason_code.in' => __('branches.validation.reason_invalid'),
                'reason_code.string' => __('branches.validation.reason_invalid'),
            ],
        );

        if ($validator->fails()) {
            return $this->validationResponse($request, $validator);
        }

        $data = $validator->validated();
        $desiredActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $expectedActive = filter_var($data['expected_is_active'], FILTER_VALIDATE_BOOLEAN);

        $changed = DB::transaction(function () use ($actor, $tenant, $target, $desiredActive, $expectedActive, $data): bool {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

            $lockedTarget = Branch::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();
            abort_unless($lockedTarget, 404);

            if ((bool) $lockedTarget->is_active !== $expectedActive) {
                throw new HttpException(409, __('branches.conflict'));
            }

            if ((bool) $lockedTarget->is_active === $desiredActive) {
                return false;
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
                'reason_code' => $data['reason_code'],
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) Str::uuid(),
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
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()
            ->whereKey($actor->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

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
}
