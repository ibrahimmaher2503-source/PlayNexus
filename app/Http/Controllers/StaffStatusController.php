<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StaffStatusController extends Controller
{
    private const STATUS_VALUES = ['invited', 'active', 'suspended', 'disabled'];

    private const MUTABLE_STATUS_VALUES = ['active', 'suspended', 'disabled'];

    private const REASON_CODES = ['staffing_change', 'access_review', 'correction'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);

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
            ->orderBy('users.id')
            ->paginate(25)
            ->withQueryString();

        return view('staff.index', compact('actor', 'tenant', 'staff'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);

        $target = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($user->getKey())
            ->firstOrFail();

        $this->denyOwnerTarget($tenant, $target);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::MUTABLE_STATUS_VALUES)],
            'expected_status' => ['required', 'string', 'in:'.implode(',', self::STATUS_VALUES)],
            'reason_code' => ['required', 'string', 'in:'.implode(',', self::REASON_CODES)],
        ], [
            'status.required' => __('staff.validation.status_required'),
            'status.in' => __('staff.validation.status_invalid'),
            'expected_status.required' => __('staff.validation.expected_status_required'),
            'expected_status.in' => __('staff.validation.expected_status_invalid'),
            'reason_code.required' => __('staff.validation.reason_required'),
            'reason_code.in' => __('staff.validation.reason_invalid'),
        ]);

        $changed = DB::transaction(function () use ($actor, $tenant, $target, $validated): bool {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

            $lockedTarget = User::query()
                ->where('tenant_id', $lockedTenant->id)
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->denyOwnerTarget($lockedTenant, $lockedTarget);

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
                'reason_code' => $validated['reason_code'],
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) Str::uuid(),
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
}
