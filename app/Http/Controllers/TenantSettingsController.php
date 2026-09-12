<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        [, $tenant] = $this->context($request);

        return view('tenant.settings', compact('tenant'));
    }

    public function update(Request $request): RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        $request->merge([
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
            'legal_name' => is_string($request->input('legal_name')) ? trim($request->input('legal_name')) : $request->input('legal_name'),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'legal_name' => ['required', 'string', 'min:2', 'max:190'],
            'default_locale' => ['required', Rule::in(['en', 'ar'])],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'currency' => ['required', Rule::in(['EGP'])],
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', Rule::in(['setup_change', 'correction'])],
        ]);

        $changed = DB::transaction(function () use ($actor, $tenant, $data): bool {
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

            if ((int) $lockedTenant->lock_version !== (int) $data['expected_lock_version']) {
                throw new HttpException(409, __('tenant_settings.conflict'));
            }

            $fields = ['name', 'legal_name', 'default_locale', 'timezone', 'currency'];
            $before = $lockedTenant->only($fields);
            $after = array_intersect_key($data, array_flip($fields));
            if ($before === $after) {
                return false;
            }

            $lockedTenant->forceFill($after + ['lock_version' => $lockedTenant->lock_version + 1])->save();
            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(), 'branch_id' => null,
                'actor_user_id' => $lockedActor->getKey(), 'actor_type' => 'user',
                'action' => 'tenant.profile.updated', 'subject_type' => 'tenant',
                'subject_id' => (string) $lockedTenant->getKey(), 'outcome' => 'success',
                'reason_code' => $data['reason_code'],
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) Str::uuid(), 'occurred_at' => now('UTC'),
            ]);

            return true;
        });

        return to_route('tenant.settings.edit')->with($changed ? 'success' : 'status_message', __($changed ? 'tenant_settings.updated' : 'tenant_settings.no_change'));
    }

    /** @return array{User, Tenant} */
    private function context(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('view', $tenant);

        return [$actor, $tenant];
    }
}
