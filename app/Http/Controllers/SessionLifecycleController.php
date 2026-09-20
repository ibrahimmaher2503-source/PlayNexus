<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PlaySession;
use App\Models\PlaySessionAdjustment;
use App\Models\PlaySessionCommand;
use App\Models\PlaySessionEvent;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionLifecycleController extends Controller
{
    private const EXTENSION_UNIT_SECONDS = 1800;

    public function state(Request $request, PlaySession $session): JsonResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $session = $this->scopedSession($session, $tenant);
        Gate::forUser($actor)->authorize('view', $session);

        return response()->json($this->statePayload($session));
    }

    public function extend(Request $request, PlaySession $session): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $request->merge([
            'expected_lock_version' => $request->input('expected_lock_version', $this->ifMatchVersion($request)),
            'idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key')),
        ]);
        $validator = Validator::make($request->all(), [
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'extension_units' => ['nullable', 'integer', 'between:1,48'],
            'added_minutes' => ['nullable', 'integer', 'between:30,86400'],
            'extension_minutes' => ['nullable', 'integer', 'between:30,86400'],
            'added_seconds' => ['nullable', 'integer', 'between:1800,86400'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            $values = array_filter([
                'extension_units' => $request->input('extension_units'),
                'added_minutes' => $request->input('added_minutes'),
                'extension_minutes' => $request->input('extension_minutes'),
                'added_seconds' => $request->input('added_seconds'),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
            if (count($values) !== 1) {
                $validator->errors()->add('extension_units', 'Exactly one extension amount is required.');

                return;
            }
            if (isset($values['added_minutes']) && ((int) $values['added_minutes'] % 30) !== 0) {
                $validator->errors()->add('added_minutes', 'Extension must use 30-minute units.');
            }
            if (isset($values['extension_minutes']) && ((int) $values['extension_minutes'] % 30) !== 0) {
                $validator->errors()->add('extension_minutes', 'Extension must use 30-minute units.');
            }
            if (isset($values['added_seconds']) && ((int) $values['added_seconds'] % self::EXTENSION_UNIT_SECONDS) !== 0) {
                $validator->errors()->add('added_seconds', 'Extension must use 30-minute units.');
            }
            $units = isset($values['extension_units'])
                ? (int) $values['extension_units']
                : (isset($values['added_seconds'])
                    ? intdiv((int) $values['added_seconds'], self::EXTENSION_UNIT_SECONDS)
                    : intdiv((int) ($values['added_minutes'] ?? $values['extension_minutes']), 30));
            if ($units > 48) {
                $validator->errors()->add('extension_units', 'An extension cannot exceed 48 thirty-minute units.');
            }
        });
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $units = $this->extensionUnits($data);
        $scoped = $this->scopedSession($session, $tenant);

        $result = DB::transaction(function () use ($request, $actor, $tenant, $scoped, $data, $units): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedSession = $this->lockSession($scoped, $lockedTenant);
            $branch = $this->lockBranch($lockedSession, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('extend', $lockedSession);
            $fingerprint = $this->fingerprint('extend', $lockedSession, $lockedActor, [
                'expected_lock_version' => (int) $data['expected_lock_version'],
                'extension_units' => $units,
            ]);
            $command = $this->existingCommand($lockedTenant, $data['idempotency_key']);
            if ($command) {
                abort_unless(
                    $command->command === 'extend'
                    && (int) $command->session_id === (int) $lockedSession->getKey()
                    && hash_equals((string) $command->fingerprint, $fingerprint),
                    409,
                    'Idempotency key conflicts with an earlier session command.',
                );

                return [$command->response_json, false];
            }
            abort_if((int) $lockedSession->lock_version !== (int) $data['expected_lock_version'], 409, 'Session version is stale.');
            abort_unless($lockedSession->status === 'active', 409, 'Only an active session can be extended.');

            $snapshot = $lockedSession->pricing_snapshot_json;
            $unitPrice = is_array($snapshot) ? $snapshot['overtime_price_minor'] ?? null : null;
            $snapshotUnit = is_array($snapshot) ? $snapshot['overtime_unit_seconds'] ?? null : null;
            $currency = is_array($snapshot) ? $snapshot['currency'] ?? null : null;
            abort_unless(
                is_int($unitPrice) && $unitPrice >= 0
                && $snapshotUnit === self::EXTENSION_UNIT_SECONDS
                && is_string($currency) && preg_match('/\A[A-Z]{3}\z/D', $currency) === 1
                && $lockedSession->expected_end_at !== null,
                409,
                'The session pricing snapshot cannot safely price an extension.',
            );
            abort_unless($units <= intdiv(PHP_INT_MAX, $unitPrice ?: 1), 409, 'The extension amount is out of bounds.');

            $now = CarbonImmutable::now('UTC');
            $beforeVersion = (int) $lockedSession->lock_version;
            $seconds = $units * self::EXTENSION_UNIT_SECONDS;
            $amount = $units * $unitPrice;
            $oldEnd = CarbonImmutable::instance($lockedSession->expected_end_at)->utc();
            $newEnd = $oldEnd->addSeconds($seconds);
            $lockedSession->forceFill([
                'expected_end_at' => $newEnd,
                'lock_version' => $beforeVersion + 1,
            ])->save();
            PlaySessionAdjustment::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'extension_units' => $units,
                'adjustment_minor' => 0,
                'reason' => 'overtime_extension',
                'expected_lock_version' => $beforeVersion,
                'applied_lock_version' => $lockedSession->lock_version,
                'request_id' => $this->requestId($request),
                'created_at' => $now,
            ]);
            $lockedSession->refresh();
            $response = $this->statePayload($lockedSession, $now) + [
                'extension_units_added' => $units,
                'extension_seconds_added' => $seconds,
                'extension_amount_minor_added' => $amount,
                'currency' => $currency,
            ];
            PlaySessionEvent::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'event_type' => 'extended',
                'from_status' => 'active',
                'to_status' => 'active',
                'reason_code' => 'overtime_extension',
                'actor_user_id' => $lockedActor->getKey(),
                'occurred_at' => $now,
                'metadata_json' => [
                    'old_expected_end_at' => $oldEnd->toIso8601String(),
                    'new_expected_end_at' => $newEnd->toIso8601String(),
                    'added_seconds' => $seconds,
                    'extension_units' => $units,
                    'unit_price_minor' => $unitPrice,
                    'amount_minor' => $amount,
                    'currency' => $currency,
                    'pricing_snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                    'lock_version_before' => $beforeVersion,
                    'lock_version_after' => $lockedSession->lock_version,
                ],
                'request_id' => $this->requestId($request),
            ]);
            $this->audit($request, $lockedActor, $lockedTenant, $branch, $lockedSession, [
                'action' => 'session.extended',
                'reason_code' => 'overtime_extension',
                'before' => [
                    'status' => 'active',
                    'expected_end_at' => $oldEnd->toIso8601String(),
                    'lock_version' => $beforeVersion,
                ],
                'after' => [
                    'status' => 'active',
                    'expected_end_at' => $newEnd->toIso8601String(),
                    'extension_units' => (int) $lockedSession->adjustments()->sum('extension_units'),
                    'extension_amount_minor' => (int) $lockedSession->adjustments()->sum('extension_units') * $unitPrice,
                    'lock_version' => $lockedSession->lock_version,
                ],
            ]);
            PlaySessionCommand::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'idempotency_key' => $data['idempotency_key'],
                'command' => 'extend',
                'fingerprint' => $fingerprint,
                'result_status' => $lockedSession->status,
                'result_lock_version' => $lockedSession->lock_version,
                'response_json' => $response,
                'executed_at' => $now,
            ]);

            return [$response, true];
        });

        if ($request->expectsJson()) {
            return response()->json($result[0] + ['created' => $result[1]]);
        }

        return to_route('sessions.index', ['branch_id' => $scoped->branch_id, 'status' => 'active'])
            ->with('success', __('sessions.actions.extend_submit'));
    }

    public function cancel(Request $request, PlaySession $session): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $request->merge([
            'expected_lock_version' => $request->input('expected_lock_version', $this->ifMatchVersion($request)),
            'idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key')),
        ]);
        $validator = Validator::make($request->all(), [
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            if (trim((string) $request->input('reason')) === '') {
                $validator->errors()->add('reason', 'A cancellation reason is required.');
            }
        });
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $data['reason'] = trim($data['reason']);
        $scoped = $this->scopedSession($session, $tenant);

        $result = DB::transaction(function () use ($request, $actor, $tenant, $scoped, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedSession = $this->lockSession($scoped, $lockedTenant);
            $branch = $this->lockBranch($lockedSession, $lockedTenant);
            Gate::forUser($lockedActor)->authorize('cancel', $lockedSession);
            $fingerprint = $this->fingerprint('cancel', $lockedSession, $lockedActor, [
                'expected_lock_version' => (int) $data['expected_lock_version'],
                'reason' => $data['reason'],
            ]);
            $command = $this->existingCommand($lockedTenant, $data['idempotency_key']);
            if ($command) {
                abort_unless(
                    $command->command === 'cancel'
                    && (int) $command->session_id === (int) $lockedSession->getKey()
                    && hash_equals((string) $command->fingerprint, $fingerprint),
                    409,
                    'Idempotency key conflicts with an earlier session command.',
                );

                return [$command->response_json, false];
            }
            abort_if((int) $lockedSession->lock_version !== (int) $data['expected_lock_version'], 409, 'Session version is stale.');
            abort_unless($lockedSession->status === 'active', 409, 'Only an active session can be cancelled.');

            $now = CarbonImmutable::now('UTC');
            $beforeVersion = (int) $lockedSession->lock_version;
            $lockedSession->forceFill([
                'status' => 'cancelled',
                'ended_at' => $now,
                'cancellation_reason' => $data['reason'],
                'ended_by_user_id' => $lockedActor->getKey(),
                'lock_version' => $beforeVersion + 1,
            ])->save();
            $lockedSession->refresh();
            $response = $this->statePayload($lockedSession, $now) + ['reason' => $data['reason']];
            PlaySessionEvent::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'event_type' => 'cancelled',
                'from_status' => 'active',
                'to_status' => 'cancelled',
                'reason_code' => 'session_cancelled',
                'actor_user_id' => $lockedActor->getKey(),
                'occurred_at' => $now,
                'metadata_json' => [
                    'reason' => $data['reason'],
                    'ended_at' => $now->toIso8601String(),
                    'lock_version_before' => $beforeVersion,
                    'lock_version_after' => $lockedSession->lock_version,
                ],
                'request_id' => $this->requestId($request),
            ]);
            $this->audit($request, $lockedActor, $lockedTenant, $branch, $lockedSession, [
                'action' => 'session.cancelled',
                'reason_code' => 'session_cancelled',
                'before' => ['status' => 'active', 'lock_version' => $beforeVersion],
                'after' => [
                    'status' => 'cancelled',
                    'ended_at' => $now->toIso8601String(),
                    'reason' => $data['reason'],
                    'lock_version' => $lockedSession->lock_version,
                ],
            ]);
            PlaySessionCommand::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'idempotency_key' => $data['idempotency_key'],
                'command' => 'cancel',
                'fingerprint' => $fingerprint,
                'result_status' => $lockedSession->status,
                'result_lock_version' => $lockedSession->lock_version,
                'response_json' => $response,
                'executed_at' => $now,
            ]);

            return [$response, true];
        });

        if ($request->expectsJson()) {
            return response()->json($result[0] + ['created' => $result[1]]);
        }

        return to_route('sessions.index', ['branch_id' => $scoped->branch_id, 'status' => 'cancelled'])
            ->with('success', __('sessions.actions.cancel_submit'));
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', PlaySession::class);

        return [$actor, $tenant];
    }

    /** @return array{User, Tenant} */
    private function lockedContext(User $actor, Tenant $tenant): array
    {
        $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
        $lockedActor = User::query()->whereKey($actor->getKey())->where('tenant_id', $lockedTenant->getKey())->where('status', 'active')->lockForUpdate()->firstOrFail();
        Gate::forUser($lockedActor)->authorize('viewAny', PlaySession::class);

        return [$lockedActor, $lockedTenant];
    }

    private function scopedSession(PlaySession $session, Tenant $tenant): PlaySession
    {
        return PlaySession::query()->whereKey($session->getKey())->where('tenant_id', $tenant->getKey())->firstOrFail();
    }

    private function lockSession(PlaySession $session, Tenant $tenant): PlaySession
    {
        return PlaySession::query()->whereKey($session->getKey())->where('tenant_id', $tenant->getKey())->lockForUpdate()->firstOrFail();
    }

    private function lockBranch(PlaySession $session, Tenant $tenant): Branch
    {
        return Branch::query()->whereKey($session->branch_id)->where('tenant_id', $tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
    }

    private function existingCommand(Tenant $tenant, string $key): ?PlaySessionCommand
    {
        return PlaySessionCommand::query()->where('tenant_id', $tenant->getKey())->where('idempotency_key', $key)->lockForUpdate()->first();
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(string $command, PlaySession $session, User $actor, array $data): string
    {
        return hash('sha256', json_encode([
            'command' => $command,
            'session_id' => (int) $session->getKey(),
            'actor_user_id' => (int) $actor->getKey(),
            ...$data,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    private function extensionUnits(array $data): int
    {
        if (isset($data['extension_units'])) {
            return (int) $data['extension_units'];
        }
        $minutes = $data['added_minutes'] ?? $data['extension_minutes'] ?? null;
        if ($minutes !== null) {
            return intdiv((int) $minutes, 30);
        }

        return intdiv((int) ($data['added_seconds'] ?? 0), self::EXTENSION_UNIT_SECONDS);
    }

    private function ifMatchVersion(Request $request): mixed
    {
        $header = $request->header('If-Match');
        if (! is_string($header)) {
            return null;
        }
        $header = trim($header);
        if (preg_match('/\A(?:W\/)?"?(\d+)"?\z/', $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /** @return array<string, mixed> */
    private function statePayload(PlaySession $session, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $running = in_array($session->status, ['active', 'paused'], true);
        $end = $session->expected_end_at;
        $isDue = $running && $end !== null && $now->greaterThanOrEqualTo($end);
        $isOverdue = $running && $end !== null && $now->greaterThan($end);
        $dueState = ! $running ? 'stopped' : ($isOverdue ? 'overdue' : ($isDue ? 'due' : 'on_time'));
        $elapsed = 0;
        if ($session->started_at !== null) {
            $asOf = $running ? $now : ($session->ended_at ?? $now);
            $elapsed = max(0, $asOf->getTimestamp() - $session->started_at->getTimestamp());
        }

        return [
            'session_id' => $session->getKey(),
            'status' => $session->status,
            'started_at' => $session->started_at?->utc()->toIso8601String(),
            'expected_end_at' => $end?->utc()->toIso8601String(),
            'ended_at' => $session->ended_at?->utc()->toIso8601String(),
            'elapsed_seconds' => $elapsed,
            'due_state' => $dueState,
            'is_due' => $isDue,
            'is_overdue' => $isOverdue,
            'extension_seconds' => (int) $session->adjustments()->sum('extension_units') * self::EXTENSION_UNIT_SECONDS,
            'extension_units' => (int) $session->adjustments()->sum('extension_units'),
            'extension_amount_minor' => $this->extensionAmount($session),
            'lock_version' => (int) $session->lock_version,
        ];
    }

    /** @param array{action:string,reason_code:string,before:array<string,mixed>,after:array<string,mixed>} $data */
    private function audit(Request $request, User $actor, Tenant $tenant, Branch $branch, PlaySession $session, array $data): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(),
            'branch_id' => $branch->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_type' => 'user',
            'action' => $data['action'],
            'subject_type' => 'play_session',
            'subject_id' => (string) $session->getKey(),
            'outcome' => 'success',
            'reason_code' => $data['reason_code'],
            'before_json' => json_encode($data['before'], JSON_THROW_ON_ERROR),
            'after_json' => json_encode($data['after'], JSON_THROW_ON_ERROR),
            'request_id' => $this->requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', Str::uuid());
    }

    private function extensionAmount(PlaySession $session): int
    {
        $snapshot = $session->pricing_snapshot_json;
        $unitPrice = is_array($snapshot) && is_int($snapshot['overtime_price_minor'] ?? null)
            ? $snapshot['overtime_price_minor']
            : 0;

        return (int) $session->adjustments()->sum('extension_units') * $unitPrice;
    }
}
