<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PlaySession;
use App\Models\PlaySessionAdjustment;
use App\Models\PlaySessionCommand;
use App\Models\PlaySessionEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SessionQuoteCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PlaySessionAdjustmentController extends Controller
{
    private const MAX_MINOR = 99_999_999_900;

    public function store(Request $request, PlaySession $session): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $request->merge(['reason' => is_string($request->input('reason')) ? trim($request->input('reason')) : $request->input('reason')]);
        $validator = Validator::make($request->all(), [
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'extension_units' => ['nullable', 'integer', 'min:0', 'max:48'],
            'adjustment_minor' => ['nullable', 'integer', 'min:-'.self::MAX_MINOR, 'max:'.self::MAX_MINOR],
            'reason' => ['required', 'string', 'min:1', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            if ((int) ($request->input('extension_units') ?? 0) === 0 && (int) ($request->input('adjustment_minor') ?? 0) === 0) {
                $validator->errors()->add('adjustment_minor', 'An extension or charge adjustment is required.');
            }
        });
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $extensionUnits = (int) ($data['extension_units'] ?? 0);
        $adjustmentMinor = (int) ($data['adjustment_minor'] ?? 0);

        $result = DB::transaction(function () use ($request, $actor, $tenant, $session, $data, $extensionUnits, $adjustmentMinor): array {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSession = PlaySession::query()
                ->whereKey($session->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $branch = Branch::query()
                ->whereKey($lockedSession->branch_id)
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($this->canAdjust($lockedActor, $lockedTenant, $branch), 403);
            $fingerprint = hash('sha256', json_encode([
                'command' => 'adjust',
                'session_id' => (int) $lockedSession->getKey(),
                'actor_user_id' => (int) $lockedActor->getKey(),
                'expected_lock_version' => (int) $data['expected_lock_version'],
                'extension_units' => $extensionUnits,
                'adjustment_minor' => $adjustmentMinor,
                'reason' => $data['reason'],
            ], JSON_THROW_ON_ERROR));
            $command = PlaySessionCommand::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();
            if ($command) {
                abort_unless(
                    $command->command === 'adjust'
                    && (int) $command->session_id === (int) $lockedSession->getKey()
                    && (int) $command->actor_user_id === (int) $lockedActor->getKey()
                    && hash_equals((string) $command->fingerprint, $fingerprint),
                    409,
                    'Idempotency key conflicts with an earlier session adjustment.',
                );

                return [$command->response_json, false];
            }
            abort_if($lockedSession->status === 'pending_payment', 409, __('sessions.checkout.status_conflict'));
            abort_unless(in_array($lockedSession->status, ['active', 'paused'], true), 409, __('sessions.checkout.status_conflict'));
            abort_if((int) $lockedSession->lock_version !== (int) $data['expected_lock_version'], 409, __('sessions.checkout.stale'));

            $now = CarbonImmutable::now('UTC');
            $existing = $lockedSession->adjustments()->get(['extension_units', 'adjustment_minor'])
                ->map(static fn (PlaySessionAdjustment $adjustment): array => [
                    'extension_units' => (int) $adjustment->extension_units,
                    'adjustment_minor' => (int) $adjustment->adjustment_minor,
                ])->all();
            try {
                $beforeQuote = SessionQuoteCalculator::calculate($lockedSession->pricing_snapshot_json, $lockedSession->started_at, $now, $existing);
                $afterQuote = SessionQuoteCalculator::calculate(
                    $lockedSession->pricing_snapshot_json,
                    $lockedSession->started_at,
                    $now,
                    [...$existing, ['extension_units' => $extensionUnits, 'adjustment_minor' => $adjustmentMinor]],
                );
            } catch (InvalidArgumentException) {
                abort(409, __('sessions.checkout.calculation_failed'));
            }

            $beforeVersion = (int) $lockedSession->lock_version;
            $afterVersion = $beforeVersion + 1;
            $adjustment = PlaySessionAdjustment::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'extension_units' => $extensionUnits,
                'adjustment_minor' => $adjustmentMinor,
                'reason' => $data['reason'],
                'expected_lock_version' => $beforeVersion,
                'applied_lock_version' => $afterVersion,
                'request_id' => $this->requestId($request),
                'created_at' => $now,
            ]);
            $lockedSession->forceFill(['lock_version' => $afterVersion])->save();
            PlaySessionEvent::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'event_type' => 'adjusted',
                'from_status' => $lockedSession->status,
                'to_status' => $lockedSession->status,
                'reason_code' => 'manager_adjustment',
                'actor_user_id' => $lockedActor->getKey(),
                'occurred_at' => $now,
                'metadata_json' => [
                    'extension_units' => $extensionUnits,
                    'adjustment_minor' => $adjustmentMinor,
                    'before_total_minor' => $beforeQuote['total_minor'],
                    'after_total_minor' => $afterQuote['total_minor'],
                ],
                'request_id' => $this->requestId($request),
            ]);
            $response = [
                'session_id' => $lockedSession->getKey(),
                'status' => $lockedSession->status,
                'adjustment_id' => $adjustment->getKey(),
                'lock_version' => $afterVersion,
                'quote' => $afterQuote,
            ];
            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $branch->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'session.adjusted',
                'subject_type' => 'play_session',
                'subject_id' => (string) $lockedSession->getKey(),
                'outcome' => 'success',
                'reason_code' => 'manager_adjustment',
                'before_json' => json_encode([
                    'status' => $lockedSession->status,
                    'lock_version' => $beforeVersion,
                    'total_minor' => $beforeQuote['total_minor'],
                    'quote' => $beforeQuote,
                ], JSON_THROW_ON_ERROR),
                'after_json' => json_encode([
                    'status' => $lockedSession->status,
                    'lock_version' => $afterVersion,
                    'adjustment_id' => $adjustment->getKey(),
                    'extension_units' => $extensionUnits,
                    'adjustment_minor' => $adjustmentMinor,
                    'reason' => $data['reason'],
                    'total_minor' => $afterQuote['total_minor'],
                    'quote' => $afterQuote,
                ], JSON_THROW_ON_ERROR),
                'request_id' => $this->requestId($request),
                'occurred_at' => $now,
            ]);

            PlaySessionCommand::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'idempotency_key' => $data['idempotency_key'],
                'command' => 'adjust',
                'fingerprint' => $fingerprint,
                'result_status' => $lockedSession->status,
                'result_lock_version' => $afterVersion,
                'response_json' => $response,
                'executed_at' => $now,
            ]);

            return [$response, true];
        });

        [$response, $created] = $result;
        if ($request->expectsJson()) {
            return response()->json($response + ['created' => $created], $created ? 201 : 200)
                ->header('Idempotent-Replayed', $created ? 'false' : 'true');
        }

        return to_route('sessions.index', ['branch_id' => $session->branch_id])
            ->with('success', 'Session adjustment saved.');
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();

        return [$actor, $tenant];
    }

    private function canAdjust(User $actor, Tenant $tenant, Branch $branch): bool
    {
        if (DB::table('tenant_owners')->where('tenant_id', $tenant->getKey())->where('user_id', $actor->getKey())->exists()) {
            return true;
        }

        return DB::table('branch_user')
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('user_id', $actor->getKey())
            ->where('role', 'branch_manager')
            ->where('is_active', true)
            ->exists();
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id');
    }
}
