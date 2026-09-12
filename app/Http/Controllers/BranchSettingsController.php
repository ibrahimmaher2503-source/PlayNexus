<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BranchSettingsController extends Controller
{
    private const PAYMENT_METHODS = ['cash'];

    public function edit(Request $request, Branch $branch): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $branch = $this->branchInTenant($branch, $tenant);
        $storedHours = DB::table('branch_opening_hours')
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->orderBy('weekday')
            ->get(['weekday', 'opens_at', 'closes_at', 'is_closed'])
            ->keyBy('weekday');

        $openingHours = $this->openingHours($storedHours);

        return view('branches.settings', compact('actor', 'tenant', 'branch', 'openingHours'));
    }

    public function update(Request $request, Branch $branch): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $branch = $this->branchInTenant($branch, $tenant);
        $payload = $this->normalizedPayload($request);
        $validator = Validator::make($payload, [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->getKey()))
                    ->ignore($branch->getKey()),
            ],
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'address_text' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(DateTimeZone::listIdentifiers())],
            'capacity' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', Rule::in(['EGP'])],
            'tax_rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'tax_mode' => ['required', 'string', Rule::in(['exclusive', 'inclusive'])],
            'receipt_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'payment_methods' => ['required', 'array', 'size:1'],
            'payment_methods.*' => ['required', 'string', Rule::in(self::PAYMENT_METHODS)],
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'opening_hours' => ['required', 'array', 'size:7'],
            'opening_hours.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'opening_hours.*.is_closed' => ['required', 'boolean'],
            'opening_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ], [
            'code.required' => __('branch_settings.validation.code_required'),
            'code.regex' => __('branch_settings.validation.code_invalid'),
            'code.unique' => __('branch_settings.validation.code_taken'),
            'name.required' => __('branch_settings.validation.name_required'),
            'name.min' => __('branch_settings.validation.name_invalid'),
            'name.max' => __('branch_settings.validation.name_invalid'),
            'timezone.in' => __('branch_settings.validation.timezone_invalid'),
            'capacity.min' => __('branch_settings.validation.capacity_invalid'),
            'currency.in' => __('branch_settings.validation.currency_invalid'),
            'tax_rate_bps.min' => __('branch_settings.validation.tax_invalid'),
            'tax_rate_bps.max' => __('branch_settings.validation.tax_invalid'),
            'tax_mode.in' => __('branch_settings.validation.tax_mode_invalid'),
            'receipt_prefix.regex' => __('branch_settings.validation.receipt_prefix_invalid'),
            'payment_methods.*.in' => __('branch_settings.validation.payment_methods_invalid'),
            'payment_methods.size' => __('branch_settings.validation.payment_methods_invalid'),
            'expected_lock_version.required' => __('branch_settings.validation.version_required'),
            'expected_lock_version.min' => __('branch_settings.validation.version_invalid'),
            'opening_hours.size' => __('branch_settings.validation.hours_required'),
            'opening_hours.*.weekday.distinct' => __('branch_settings.validation.weekdays_unique'),
            'opening_hours.*.weekday.between' => __('branch_settings.validation.weekday_invalid'),
            'opening_hours.*.opens_at.date_format' => __('branch_settings.validation.time_invalid'),
            'opening_hours.*.closes_at.date_format' => __('branch_settings.validation.time_invalid'),
        ]);
        $validator->after(function ($validator) use ($payload): void {
            $this->validateOpeningHours($validator, $payload['opening_hours'] ?? []);
        });

        if ($validator->fails()) {
            return $this->validationResponse($request, $validator);
        }

        $data = $validator->validated();
        $desired = [
            'code' => $data['code'],
            'name' => $data['name'],
            'address_text' => $data['address_text'] ?? null,
            'timezone' => $data['timezone'],
            'capacity' => (int) $data['capacity'],
            'currency' => $data['currency'],
            'tax_rate_bps' => (int) $data['tax_rate_bps'],
            'tax_mode' => $data['tax_mode'],
            'receipt_prefix' => $data['receipt_prefix'],
            'payment_methods' => array_values($data['payment_methods']),
        ];
        $desiredHours = $this->canonicalHours($data['opening_hours']);
        $expectedVersion = (int) $data['expected_lock_version'];

        $result = DB::transaction(function () use ($actor, $tenant, $branch, $desired, $desiredHours, $expectedVersion): array {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('view', $lockedTenant);

            $lockedBranch = Branch::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->whereKey($branch->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $storedHours = DB::table('branch_opening_hours')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('branch_id', $lockedBranch->getKey())
                ->lockForUpdate()
                ->get(['weekday', 'opens_at', 'closes_at', 'is_closed'])
                ->keyBy('weekday');

            if ((int) $lockedBranch->lock_version !== $expectedVersion) {
                throw new HttpException(409, __('branch_settings.conflict'));
            }

            $current = $this->branchState($lockedBranch, $storedHours);
            if ($this->sameState($current, $desired, $desiredHours)) {
                return ['changed' => false, 'lock_version' => (int) $lockedBranch->lock_version];
            }

            $before = $this->auditSnapshot($current);
            $nextVersion = (int) $lockedBranch->lock_version + 1;
            $now = now('UTC');

            DB::table('branches')
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('id', $lockedBranch->getKey())
                ->update([
                    ...$desired,
                    'payment_methods' => json_encode($desired['payment_methods'], JSON_THROW_ON_ERROR),
                    'lock_version' => $nextVersion,
                    'updated_at' => $now,
                ]);

            $hours = [];
            foreach ($desiredHours as $weekday => $hoursForDay) {
                $hours[] = [
                    'tenant_id' => $lockedTenant->getKey(),
                    'branch_id' => $lockedBranch->getKey(),
                    'weekday' => $weekday,
                    'opens_at' => $hoursForDay['opens_at'],
                    'closes_at' => $hoursForDay['closes_at'],
                    'is_closed' => $hoursForDay['is_closed'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('branch_opening_hours')->upsert(
                $hours,
                ['tenant_id', 'branch_id', 'weekday'],
                ['opens_at', 'closes_at', 'is_closed', 'updated_at'],
            );

            $after = $desired;
            $after['lock_version'] = $nextVersion;
            $after['opening_hours'] = $desiredHours;
            $before['opening_hours'] = $current['opening_hours'];
            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $lockedBranch->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'branch.settings.updated',
                'subject_type' => 'branch',
                'subject_id' => (string) $lockedBranch->getKey(),
                'outcome' => 'success',
                'reason_code' => 'setup_change',
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'occurred_at' => $now,
            ]);

            return ['changed' => true, 'lock_version' => $nextVersion];
        });

        $message = $result['changed']
            ? __('branch_settings.updated')
            : __('branch_settings.no_change');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'changed' => $result['changed'],
                'lock_version' => $result['lock_version'],
            ]);
        }

        return to_route('branches.settings', $branch)->with(
            $result['changed'] ? 'success' : 'status_message',
            $message,
        );
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

    private function branchInTenant(Branch $branch, Tenant $tenant): Branch
    {
        return Branch::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($branch->getKey())
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function normalizedPayload(Request $request): array
    {
        $payload = $request->all();
        foreach (['code', 'name', 'timezone', 'currency', 'tax_mode', 'receipt_prefix'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }
        if (isset($payload['code']) && is_string($payload['code'])) {
            $payload['code'] = strtoupper($payload['code']);
        }
        if (isset($payload['currency']) && is_string($payload['currency'])) {
            $payload['currency'] = strtoupper($payload['currency']);
        }
        if (isset($payload['receipt_prefix']) && is_string($payload['receipt_prefix'])) {
            $payload['receipt_prefix'] = strtoupper($payload['receipt_prefix']);
        }
        if (array_key_exists('address_text', $payload) && is_string($payload['address_text'])) {
            $payload['address_text'] = trim($payload['address_text']) === '' ? null : trim($payload['address_text']);
        }
        if (isset($payload['payment_methods']) && is_string($payload['payment_methods'])) {
            $payload['payment_methods'] = [$payload['payment_methods']];
        }

        $openingHours = $payload['opening_hours'] ?? null;
        if (is_array($openingHours) && count($openingHours) === 7) {
            $keyedByWeekday = true;
            foreach ($openingHours as $weekday => $day) {
                if (! in_array((string) $weekday, ['1', '2', '3', '4', '5', '6', '7'], true) || ! is_array($day) || array_key_exists('weekday', $day)) {
                    $keyedByWeekday = false;
                    break;
                }
            }
            if ($keyedByWeekday) {
                $openingHours = array_map(
                    fn (int|string $weekday, mixed $day): array => ['weekday' => (int) $weekday, ...$day],
                    array_keys($openingHours),
                    array_values($openingHours),
                );
            }
        }
        $payload['opening_hours'] = $openingHours;

        return $payload;
    }

    private function validateOpeningHours($validator, mixed $hours): void
    {
        if (! is_array($hours)) {
            return;
        }

        foreach (array_values($hours) as $index => $day) {
            if (! is_array($day) || ! array_key_exists('is_closed', $day)) {
                continue;
            }
            $closed = filter_var($day['is_closed'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($closed === null) {
                continue;
            }
            $opensAt = $day['opens_at'] ?? null;
            $closesAt = $day['closes_at'] ?? null;
            if ($closed && ($opensAt !== null || $closesAt !== null)) {
                $validator->errors()->add("opening_hours.$index.opens_at", __('branch_settings.validation.closed_times'));
            } elseif (! $closed && ($opensAt === null || $closesAt === null)) {
                $validator->errors()->add("opening_hours.$index.opens_at", __('branch_settings.validation.open_times_required'));
            } elseif (! $closed && is_string($opensAt) && is_string($closesAt) && $opensAt >= $closesAt) {
                $validator->errors()->add("opening_hours.$index.closes_at", __('branch_settings.validation.times_order'));
            }
        }
    }

    /** @param array<int|string, mixed> $hours @return array<int, array{opens_at:?string, closes_at:?string, is_closed:bool}> */
    private function canonicalHours(array $hours): array
    {
        $canonical = [];
        foreach ($hours as $day) {
            $weekday = (int) $day['weekday'];
            $canonical[$weekday] = [
                'opens_at' => $day['opens_at'] ?? null,
                'closes_at' => $day['closes_at'] ?? null,
                'is_closed' => (bool) filter_var($day['is_closed'], FILTER_VALIDATE_BOOLEAN),
            ];
        }
        ksort($canonical);

        return $canonical;
    }

    /** @return array<string, mixed> */
    private function branchState(Branch $branch, $storedHours): array
    {
        $paymentMethods = [];
        if (is_array($branch->payment_methods)) {
            $paymentMethods = array_values($branch->payment_methods);
        } elseif (is_string($branch->payment_methods ?? null) && $branch->payment_methods !== '') {
            $decoded = json_decode($branch->payment_methods, true);
            $paymentMethods = is_array($decoded) ? array_values($decoded) : [];
        }

        return [
            'code' => $branch->code,
            'name' => $branch->name,
            'address_text' => $branch->address_text,
            'timezone' => $branch->timezone,
            'capacity' => (int) $branch->capacity,
            'currency' => $branch->currency,
            'tax_rate_bps' => (int) $branch->tax_rate_bps,
            'tax_mode' => $branch->tax_mode,
            'receipt_prefix' => $branch->receipt_prefix,
            'payment_methods' => $paymentMethods,
            'lock_version' => (int) $branch->lock_version,
            'opening_hours' => $this->openingHours($storedHours),
        ];
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $desired @param array<int, array{opens_at:?string, closes_at:?string, is_closed:bool}> $desiredHours */
    private function sameState(array $current, array $desired, array $desiredHours): bool
    {
        foreach ($desired as $field => $value) {
            if ($current[$field] !== $value) {
                return false;
            }
        }

        foreach ($desiredHours as $weekday => $hours) {
            $currentDay = $current['opening_hours'][$weekday] ?? [];
            if (
                ($currentDay['opens_at'] ?? null) !== $hours['opens_at']
                || ($currentDay['closes_at'] ?? null) !== $hours['closes_at']
                || (bool) ($currentDay['is_closed'] ?? true) !== $hours['is_closed']
            ) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(array $state): array
    {
        return $state;
    }

    /** @return array<int, array{weekday:int, opens_at:?string, closes_at:?string, is_closed:bool}> */
    private function openingHours($storedHours): array
    {
        $hours = [];
        foreach (range(1, 7) as $weekday) {
            $stored = $storedHours->get($weekday);
            $hours[$weekday] = [
                'weekday' => $weekday,
                'opens_at' => $this->timeValue($stored?->opens_at),
                'closes_at' => $this->timeValue($stored?->closes_at),
                'is_closed' => $stored === null ? true : (bool) $stored->is_closed,
            ];
        }

        return $hours;
    }

    private function timeValue(mixed $time): ?string
    {
        return $time === null ? null : substr((string) $time, 0, 5);
    }

    private function validationResponse(Request $request, $validator): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('branch_settings.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        return back()->withErrors($validator)->withInput();
    }
}
