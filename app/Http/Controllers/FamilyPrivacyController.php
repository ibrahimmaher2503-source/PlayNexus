<?php

namespace App\Http\Controllers;

use App\Models\FamilyRetentionHold;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FamilyRetention;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FamilyPrivacyController extends Controller
{
    private const HOLD_CATEGORIES = ['legal', 'financial', 'safety', 'complaint', 'litigation'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->context($request);
        $rows = FamilyRetention::dryRun($tenant);
        $holdCandidates = Guardian::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'status']);
        $activeHolds = FamilyRetentionHold::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->with(['guardian:id,full_name', 'placedBy:id,name'])
            ->orderByDesc('placed_at')
            ->limit(100)
            ->get();

        return view('families.privacy', compact('actor', 'tenant', 'rows', 'holdCandidates', 'activeHolds'));
    }

    public function storeHold(Request $request): RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        $data = $request->validate([
            'guardian_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(self::HOLD_CATEGORIES)],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $created = DB::transaction(function () use ($request, $actor, $tenant, $data): bool {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
            $lockedActor = User::query()->whereKey($actor->getKey())->where('tenant_id', $lockedTenant->getKey())->where('status', 'active')->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);
            $guardian = Guardian::query()->where('tenant_id', $lockedTenant->getKey())->whereKey($data['guardian_id'])->lockForUpdate()->firstOrFail();
            if (FamilyRetentionHold::query()->where('tenant_id', $lockedTenant->getKey())->where('guardian_id', $guardian->getKey())->where('status', 'active')->exists()) {
                return false;
            }
            $now = now('UTC');
            $hold = FamilyRetentionHold::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'guardian_id' => $guardian->getKey(),
                'category' => $data['category'],
                'reason' => $data['reason'],
                'status' => 'active',
                'placed_by_user_id' => $lockedActor->getKey(),
                'placed_at' => $now,
                'request_id' => (string) $request->attributes->get('request_id', Str::uuid()),
            ]);
            $this->audit($request, $lockedActor, $lockedTenant, 'family.retention_hold.placed', $hold, [
                'guardian_id' => (string) $guardian->getKey(), 'category' => $data['category'],
            ]);

            return true;
        });

        return to_route('families.privacy.index')->with($created ? 'success' : 'status_message', __($created ? 'privacy.hold_created' : 'privacy.hold_exists'));
    }

    public function releaseHold(Request $request, FamilyRetentionHold $familyRetentionHold): RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        abort_unless((int) $familyRetentionHold->tenant_id === (int) $tenant->getKey(), 404);
        $data = $request->validate(['release_reason' => ['required', 'string', 'min:10', 'max:500']]);

        $released = DB::transaction(function () use ($request, $actor, $tenant, $familyRetentionHold, $data): bool {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
            $lockedActor = User::query()->whereKey($actor->getKey())->where('tenant_id', $lockedTenant->getKey())->where('status', 'active')->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);
            $hold = FamilyRetentionHold::query()->where('tenant_id', $lockedTenant->getKey())->whereKey($familyRetentionHold->getKey())->lockForUpdate()->firstOrFail();
            if ($hold->status !== 'active') {
                return false;
            }
            $hold->forceFill([
                'status' => 'released', 'released_by_user_id' => $lockedActor->getKey(),
                'released_at' => now('UTC'), 'release_reason' => $data['release_reason'],
            ])->save();
            $this->audit($request, $lockedActor, $lockedTenant, 'family.retention_hold.released', $hold, [
                'guardian_id' => (string) $hold->guardian_id, 'status' => 'released',
            ]);

            return true;
        });

        return to_route('families.privacy.index')->with($released ? 'success' : 'status_message', __($released ? 'privacy.hold_released' : 'privacy.hold_already_released'));
    }

    /** @return array{User, Tenant} */
    private function context(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('view', $tenant);

        return [$actor, $tenant];
    }

    private function audit(Request $request, User $actor, Tenant $tenant, string $action, FamilyRetentionHold $hold, array $after): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(), 'branch_id' => null, 'actor_user_id' => $actor->getKey(), 'actor_type' => 'user',
            'action' => $action, 'subject_type' => 'family_retention_hold', 'subject_id' => (string) $hold->getKey(),
            'outcome' => 'success', 'reason_code' => 'privacy_retention', 'before_json' => null,
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => (string) $request->attributes->get('request_id', Str::uuid()), 'occurred_at' => now('UTC'),
        ]);
    }
}
