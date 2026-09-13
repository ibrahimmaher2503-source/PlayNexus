<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\PlaySession;
use App\Models\PlaySessionEvent;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketScan;
use App\Models\User;
use App\Policies\PlaySessionPolicy;
use App\Support\PhoneNormalizer;
use App\Support\SessionQuoteCalculator;
use App\Support\TicketEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlaySessionController extends Controller
{
    private const STATUSES = ['active', 'all', 'paused', 'pending_payment', 'completed', 'cancelled'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $policy = app(PlaySessionPolicy::class);
        $branches = $actor->accessibleBranches()
            ->where('branches.tenant_id', $tenant->getKey())
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->orderBy('branches.id')
            ->get()
            ->filter(fn (Branch $branch): bool => $policy->viewBranch($actor, $branch))
            ->values();

        $selectedBranchId = $this->selectedBranchId($request, $branches);
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ])->validate();
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'active';
        $branchIds = $branches->modelKeys();

        $sessionsQuery = PlaySession::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('branch_id', $branchIds === [] ? [-1] : $branchIds)
            ->with([
                'branch:id,tenant_id,name,timezone,capacity',
                'child:id,tenant_id,full_name',
                'child.guardians' => fn (BelongsToMany $query): BelongsToMany => $query
                    ->where('guardians.status', 'active')
                    ->wherePivot('is_active', true)
                    ->wherePivot('can_check_out', true)
                    ->wherePivotNotNull('verified_at')
                    ->orderBy('guardians.full_name'),
                'guardian:id,tenant_id,full_name',
                'ticket:id,tenant_id,branch_id,display_code,price_snapshot_json',
            ]);

        if ($selectedBranchId !== null) {
            $sessionsQuery->where('branch_id', $selectedBranchId);
        }
        if ($status !== 'all') {
            $sessionsQuery->where('status', $status);
        }
        if ($search !== '') {
            $phone = PhoneNormalizer::normalize($search);
            $sessionsQuery->where(function (EloquentBuilder $query) use ($search, $phone): void {
                $query->whereHas('child', fn (EloquentBuilder $child): EloquentBuilder => $child->where('full_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('guardian', function (EloquentBuilder $guardian) use ($search, $phone): void {
                        $guardian->where('full_name', 'like', '%'.$search.'%');
                        if ($phone !== null) {
                            $guardian->orWhere('phone_e164', $phone);
                        }
                    });
            });
        }

        $sessions = $sessionsQuery
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $serverNow = CarbonImmutable::now('UTC');
        $sessions->getCollection()->each(static function (PlaySession $session) use ($serverNow, $actor, $policy): void {
            $estimate = null;
            $snapshot = $session->pricing_snapshot_json;
            if ($session->status === 'active' && $session->started_at !== null && is_array($snapshot)) {
                try {
                    $estimate = SessionQuoteCalculator::calculate(
                        $snapshot,
                        $session->started_at,
                        $serverNow,
                    );
                } catch (InvalidArgumentException) {
                    // A malformed immutable snapshot must never produce a financial estimate.
                }
            }
            $session->setAttribute('live_estimate', $estimate);
            $session->setAttribute('can_checkout', $session->status === 'active' && $policy->checkout($actor, $session));
            $session->setAttribute('can_override_checkout', $session->status === 'active' && $policy->overrideCheckout($actor, $session));
        });

        $branchOccupancy = [];
        foreach ($branches as $branch) {
            $branchOccupancy[(int) $branch->getKey()] = [
                'active_count' => 0,
                'capacity' => (int) $branch->capacity,
            ];
        }
        if ($branchIds !== []) {
            $counts = PlaySession::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('branch_id', $branchIds)
                ->whereIn('status', ['active', 'paused'])
                ->selectRaw('branch_id, COUNT(*) AS active_count')
                ->groupBy('branch_id')
                ->get();
            foreach ($counts as $count) {
                $branchOccupancy[(int) $count->branch_id]['active_count'] = (int) $count->active_count;
            }
        }

        $checkInBranchIds = $branches
            ->filter(fn (Branch $branch): bool => $policy->checkIn($actor, $branch))
            ->modelKeys();
        $managerBoardFirst = DB::table('tenant_owners')
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $actor->getKey())
            ->exists()
            || DB::table('branch_user')
                ->where('tenant_id', $tenant->getKey())
                ->where('user_id', $actor->getKey())
                ->where('is_active', true)
                ->where('role', 'branch_manager')
                ->exists();

        return view('sessions.index', [
            'actor' => $actor,
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'sessions' => $sessions,
            'filters' => ['q' => $search, 'status' => $status],
            'serverNow' => $serverNow,
            'checkInKey' => (string) Str::uuid(),
            'checkInBranchIds' => $checkInBranchIds,
            'managerBoardFirst' => $managerBoardFirst,
            'branchOccupancy' => $branchOccupancy,
        ]);
    }

    public function checkIn(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        if (is_string($request->input('code'))) {
            $request->merge(['code' => trim($request->input('code'))]);
        }
        $request->merge([
            'idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key')),
        ]);
        $validator = Validator::make($request->all(), [
            'branch_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'min:6', 'max:512'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        if ($validator->fails()) {
            $request->request->remove('code');
            throw new ValidationException($validator);
        }
        $data = $validator->validated();
        $request->request->remove('code');
        $lookup = str_starts_with(Str::upper($data['code']), 'PN-')
            ? Str::upper($data['code'])
            : $data['code'];
        $inputHash = hash('sha256', $lookup);
        $fingerprint = $this->fingerprint((int) $data['branch_id'], $inputHash);

        $result = DB::transaction(function () use ($request, $actor, $tenant, $data, $lookup, $inputHash, $fingerprint): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $branch = $this->lockedBranch($lockedTenant, $lockedActor, (int) $data['branch_id']);
            Gate::forUser($lockedActor)->authorize('checkIn', [PlaySession::class, $branch]);

            $existing = TicketScan::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('scanned_by_user_id', $lockedActor->getKey())
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ($existing->scan_purpose !== 'check_in' || ! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw new HttpException(409, __('tickets.errors.idempotency_conflict'));
                }

                return $this->replayedResult($existing, $lockedTenant, $branch);
            }

            $ticket = Ticket::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where(function (EloquentBuilder $query) use ($lookup, $inputHash): void {
                    $query->where('code_hash', $inputHash)
                        ->orWhere('display_code', Str::upper($lookup));
                })
                ->lockForUpdate()
                ->first();

            $child = null;
            if ($ticket && (int) $ticket->branch_id === (int) $branch->getKey()) {
                $child = Child::query()
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->whereKey($ticket->child_id)
                    ->lockForUpdate()
                    ->first();
            }
            $resultCode = $this->safeEligibilityResult($ticket, $branch);
            $session = null;
            if ($resultCode === 'accepted') {
                $snapshot = $child ? $this->sessionSnapshot($ticket, $lockedTenant, $branch) : null;
                if (! $child || $snapshot === null) {
                    $resultCode = 'invalid_ticket';
                } else {
                    $liveSession = PlaySession::query()
                        ->where('tenant_id', $lockedTenant->getKey())
                        ->where('child_id', $child->getKey())
                        ->whereIn('status', ['active', 'paused'])
                        ->lockForUpdate()
                        ->first();
                    if ($liveSession) {
                        $resultCode = 'active_session_exists';
                    } elseif ($this->activeSessionCount($lockedTenant, $branch) >= (int) $branch->capacity) {
                        $resultCode = 'capacity_full';
                    } else {
                        $now = CarbonImmutable::now('UTC');
                        $ticketWasLocked = $ticket->assignment_locked_at !== null;
                        $ticket->forceFill([
                            'status' => 'consumed',
                            'consumed_at' => $now,
                            'uses_count' => 1,
                            'assignment_locked_at' => $ticket->assignment_locked_at ?? $now,
                            'lock_version' => (int) $ticket->lock_version + 1,
                        ])->save();

                        [$pricingSnapshot, $duration, $pricingRuleId] = $snapshot;
                        $session = PlaySession::query()->create([
                            'tenant_id' => $lockedTenant->getKey(),
                            'branch_id' => $branch->getKey(),
                            'child_id' => $ticket->child_id,
                            'guardian_id' => $ticket->guardian_id,
                            'ticket_id' => $ticket->getKey(),
                            'pricing_rule_id' => $pricingRuleId,
                            'status' => 'active',
                            'started_at' => $now,
                            'expected_end_at' => $now->addSeconds($duration),
                            'pricing_snapshot_json' => $pricingSnapshot,
                            'created_by_user_id' => $lockedActor->getKey(),
                            'lock_version' => 1,
                        ]);

                        PlaySessionEvent::query()->create([
                            'tenant_id' => $lockedTenant->getKey(),
                            'session_id' => $session->getKey(),
                            'event_type' => 'checked_in',
                            'from_status' => null,
                            'to_status' => 'active',
                            'reason_code' => 'ticket_check_in',
                            'actor_user_id' => $lockedActor->getKey(),
                            'occurred_at' => $now,
                            'metadata_json' => [
                                'ticket_id' => (string) $ticket->getKey(),
                                'ticket_status' => 'consumed',
                                'ticket_was_assignment_locked' => $ticketWasLocked,
                            ],
                            'request_id' => $this->requestId($request),
                        ]);

                        $this->audit($request, $lockedActor, $lockedTenant, $branch, $session, $ticketWasLocked);
                    }
                }
            }

            $scan = TicketScan::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'ticket_id' => $ticket?->getKey(),
                'branch_id' => $branch->getKey(),
                'scanned_by_user_id' => $lockedActor->getKey(),
                'scanned_at' => now('UTC'),
                'scan_purpose' => 'check_in',
                'result' => $resultCode,
                'code_hash' => $ticket?->code_hash ?? $inputHash,
                'idempotency_key' => $data['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'request_id' => $this->requestId($request),
            ]);

            return [
                'scan' => $scan,
                'session' => $session,
                'ticket' => $ticket && $resultCode !== 'wrong_branch' ? $ticket : null,
                'created' => $session !== null,
            ];
        });

        return $this->checkInResponse($request, $result);
    }

    public function prepareCheckout(Request $request, PlaySession $session): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $data = Validator::make($request->all(), [
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'verification_method' => ['required', Rule::in(['phone_last_four', 'manager_override'])],
            'guardian_id' => ['nullable', 'required_if:verification_method,phone_last_four', 'integer'],
            'phone_last_four' => ['nullable', 'required_if:verification_method,phone_last_four', 'digits:4'],
            'override_reason' => ['nullable', 'required_if:verification_method,manager_override', 'string', 'min:10', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        $result = DB::transaction(function () use ($request, $actor, $tenant, $session, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $lockedSession = PlaySession::query()
                ->whereKey($session->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $branch = $this->lockedBranch($lockedTenant, $lockedActor, (int) $lockedSession->branch_id);
            Gate::forUser($lockedActor)->authorize('checkout', $lockedSession);

            $fingerprint = hash('sha256', json_encode([
                'session_id' => (int) $lockedSession->getKey(),
                'expected_lock_version' => (int) $data['expected_lock_version'],
                'verification_method' => $data['verification_method'],
                'guardian_id' => isset($data['guardian_id']) ? (int) $data['guardian_id'] : null,
                'phone_last_four' => $data['phone_last_four'] ?? null,
                'override_reason' => isset($data['override_reason']) ? trim($data['override_reason']) : null,
            ], JSON_THROW_ON_ERROR));

            $keyOwner = PlaySession::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('checkout_idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();
            if ($keyOwner) {
                abort_unless($keyOwner->is($lockedSession) && hash_equals((string) $keyOwner->checkout_fingerprint, $fingerprint), 409, __('sessions.checkout.idempotency_conflict'));

                return [$keyOwner, false];
            }

            abort_if((int) $lockedSession->lock_version !== (int) $data['expected_lock_version'], 409, __('sessions.checkout.stale'));
            abort_unless($lockedSession->status === 'active', 409, __('sessions.checkout.status_conflict'));

            $guardian = null;
            $overrideReason = null;
            if ($data['verification_method'] === 'phone_last_four') {
                $relationship = DB::table('guardian_child')
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->where('guardian_id', (int) $data['guardian_id'])
                    ->where('child_id', (int) $lockedSession->child_id)
                    ->where('is_active', true)
                    ->where('can_check_out', true)
                    ->whereNotNull('verified_at')
                    ->lockForUpdate()
                    ->first();
                abort_unless($relationship !== null, 404);

                $guardian = Guardian::query()
                    ->where('guardians.tenant_id', $lockedTenant->getKey())
                    ->where('guardians.id', (int) $data['guardian_id'])
                    ->where('guardians.status', 'active')
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! hash_equals(substr($guardian->phone_e164, -4), (string) $data['phone_last_four'])) {
                    throw ValidationException::withMessages([
                        'phone_last_four' => __('sessions.checkout.phone_mismatch'),
                    ]);
                }
            } else {
                Gate::forUser($lockedActor)->authorize('overrideCheckout', $lockedSession);
                $overrideReason = trim((string) $data['override_reason']);
            }

            try {
                $preparedAt = CarbonImmutable::now('UTC');
                $quote = SessionQuoteCalculator::calculate($lockedSession->pricing_snapshot_json, $lockedSession->started_at, $preparedAt);
            } catch (InvalidArgumentException) {
                abort(409, __('sessions.checkout.calculation_failed'));
            }

            $beforeVersion = (int) $lockedSession->lock_version;
            $lockedSession->forceFill([
                'status' => 'pending_payment',
                'checkout_idempotency_key' => $data['idempotency_key'],
                'checkout_fingerprint' => $fingerprint,
                'checkout_guardian_id' => $guardian?->getKey(),
                'checkout_verification_method' => $data['verification_method'],
                'checkout_override_reason' => $overrideReason,
                'checkout_verified_by_user_id' => $lockedActor->getKey(),
                'checkout_verified_at' => $preparedAt,
                'checkout_prepared_at' => $preparedAt,
                'checkout_snapshot_json' => $quote,
                'checkout_amount_due_minor' => $quote['total_minor'],
                'lock_version' => $beforeVersion + 1,
            ])->save();

            PlaySessionEvent::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'session_id' => $lockedSession->getKey(),
                'event_type' => 'checkout_prepared',
                'from_status' => 'active',
                'to_status' => 'pending_payment',
                'reason_code' => $data['verification_method'] === 'manager_override' ? 'guardian_manager_override' : 'guardian_verified',
                'actor_user_id' => $lockedActor->getKey(),
                'occurred_at' => $preparedAt,
                'metadata_json' => ['amount_due_minor' => $quote['total_minor'], 'currency' => $quote['currency']],
                'request_id' => $this->requestId($request),
            ]);
            $this->auditCheckout($request, $lockedActor, $lockedTenant, $branch, $lockedSession, $beforeVersion);

            return [$lockedSession, true];
        });

        [$prepared, $created] = $result;
        if ($request->expectsJson()) {
            return response()->json([
                'session_id' => $prepared->getKey(),
                'status' => $prepared->status,
                'amount_due_minor' => $prepared->checkout_amount_due_minor,
                'currency' => data_get($prepared->checkout_snapshot_json, 'currency'),
                'created' => $created,
            ], $created ? 201 : 200);
        }

        return to_route('sessions.index', ['branch_id' => $prepared->branch_id, 'status' => 'pending_payment'])
            ->with('success', __('sessions.checkout.prepared'));
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
        Gate::forUser($lockedActor)->authorize('viewAny', PlaySession::class);

        return [$lockedActor, $lockedTenant];
    }

    private function lockedBranch(Tenant $tenant, User $actor, int $branchId): Branch
    {
        return $actor->accessibleBranches()
            ->where('branches.tenant_id', $tenant->getKey())
            ->where('branches.is_active', true)
            ->whereKey($branchId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @param Collection<int, Branch> $branches */
    private function selectedBranchId(Request $request, Collection $branches): ?int
    {
        $selected = $request->query('branch_id', $request->session()->get('branch_id'));
        if ($request->has('branch_id')) {
            if ($selected === '') {
                return null;
            }
            abort_unless(
                (is_int($selected) || is_string($selected))
                && ctype_digit((string) $selected)
                && $branches->contains('id', (int) $selected),
                404,
            );
        }
        if (! is_int($selected) && ! is_string($selected)) {
            return null;
        }

        return ctype_digit((string) $selected) && $branches->contains('id', (int) $selected)
            ? (int) $selected
            : null;
    }

    private function safeEligibilityResult(?Ticket $ticket, Branch $branch): string
    {
        if ($ticket === null) {
            return 'not_found';
        }
        if ((int) $ticket->branch_id !== (int) $branch->getKey()) {
            return 'wrong_branch';
        }
        if ($ticket->status === 'issued' && $this->sessionSnapshot($ticket, null, $branch) === null) {
            return 'invalid_ticket';
        }

        return TicketEligibility::result($ticket, $branch);
    }

    /** @return array{array<string, mixed>, int, int}|null */
    private function sessionSnapshot(Ticket $ticket, ?Tenant $tenant, Branch $branch): ?array
    {
        $snapshot = $ticket->price_snapshot_json;
        if (! is_array($snapshot)) {
            return null;
        }
        $timezone = $snapshot['branch_timezone'] ?? null;
        $duration = $snapshot['base_duration_seconds'] ?? null;
        $ruleId = $snapshot['pricing_rule_id'] ?? null;
        if (! is_string($timezone) || $timezone === '' || ! is_numeric($duration) || (int) $duration < 1 || (int) $duration > 31536000) {
            return null;
        }
        if (! is_numeric($ruleId) || (int) $ruleId < 1) {
            return null;
        }
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable) {
            return null;
        }
        if ($tenant !== null && ! PricingRule::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->whereKey((int) $ruleId)
            ->exists()) {
            return null;
        }

        return [$snapshot, (int) $duration, (int) $ruleId];
    }

    private function activeSessionCount(Tenant $tenant, Branch $branch): int
    {
        return (int) PlaySession::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->whereIn('status', ['active', 'paused'])
            ->count();
    }

    /** @return array{scan: TicketScan, session: ?PlaySession, ticket: ?Ticket, created: bool} */
    private function replayedResult(TicketScan $existing, Tenant $tenant, Branch $branch): array
    {
        $ticket = $existing->ticket_id === null
            ? null
            : Ticket::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($existing->ticket_id)
                ->lockForUpdate()
                ->first();
        if ($ticket && (int) $ticket->branch_id === (int) $branch->getKey()) {
            Child::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($ticket->child_id)
                ->lockForUpdate()
                ->first();
        }
        $session = $existing->result === 'accepted' && $ticket
            ? PlaySession::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('ticket_id', $ticket->getKey())
                ->lockForUpdate()
                ->first()
            : null;

        return [
            'scan' => $existing,
            'session' => $session,
            'ticket' => $ticket && $existing->result !== 'wrong_branch' ? $ticket : null,
            'created' => false,
        ];
    }

    private function checkInResponse(Request $request, array $result): JsonResponse|RedirectResponse
    {
        /** @var TicketScan $scan */
        $scan = $result['scan'];
        /** @var ?PlaySession $session */
        $session = $result['session'];
        /** @var ?Ticket $ticket */
        $ticket = $result['ticket'];
        $accepted = $scan->result === 'accepted' && $session !== null;
        $created = (bool) $result['created'];
        if ($request->expectsJson()) {
            $status = $accepted ? ($created ? 201 : 200) : (in_array($scan->result, ['active_session_exists', 'capacity_full'], true) ? 409 : 422);

            return response()->json([
                'accepted' => $accepted,
                'result' => $scan->result,
                'created' => $created,
                'session_id' => $session?->getKey(),
                'ticket_id' => $ticket?->getKey(),
                'status' => $session?->status,
                'started_at' => $session?->started_at?->toIso8601String(),
                'expected_end_at' => $session?->expected_end_at?->toIso8601String(),
                'scanned_at' => $scan->scanned_at->toIso8601String(),
            ], $status);
        }

        return to_route('sessions.index', ['branch_id' => $scan->branch_id])
            ->with('session_checkin_result', $scan->result)
            ->with('session_checkin_id', $session?->getKey());
    }

    private function fingerprint(int $branchId, string $codeHash): string
    {
        return hash('sha256', json_encode([
            'branch_id' => $branchId,
            'code_hash' => $codeHash,
            'scan_purpose' => 'check_in',
        ], JSON_THROW_ON_ERROR));
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', Str::uuid());
    }

    private function audit(Request $request, User $actor, Tenant $tenant, Branch $branch, PlaySession $session, bool $ticketWasLocked): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(),
            'branch_id' => $branch->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_type' => 'user',
            'action' => 'session.checked_in',
            'subject_type' => 'play_session',
            'subject_id' => (string) $session->getKey(),
            'outcome' => 'success',
            'reason_code' => 'ticket_check_in',
            'before_json' => json_encode([
                'ticket_status' => 'issued',
                'ticket_assignment_locked' => $ticketWasLocked,
                'session_status' => null,
            ], JSON_THROW_ON_ERROR),
            'after_json' => json_encode([
                'ticket_status' => 'consumed',
                'ticket_assignment_locked' => true,
                'session_status' => 'active',
                'session_id' => (string) $session->getKey(),
            ], JSON_THROW_ON_ERROR),
            'request_id' => $this->requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }

    private function auditCheckout(Request $request, User $actor, Tenant $tenant, Branch $branch, PlaySession $session, int $beforeVersion): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(),
            'branch_id' => $branch->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_type' => 'user',
            'action' => 'session.checkout_prepared',
            'subject_type' => 'play_session',
            'subject_id' => (string) $session->getKey(),
            'outcome' => 'success',
            'reason_code' => $session->checkout_verification_method === 'manager_override' ? 'guardian_manager_override' : 'guardian_verified',
            'before_json' => json_encode(['status' => 'active', 'lock_version' => $beforeVersion], JSON_THROW_ON_ERROR),
            'after_json' => json_encode([
                'status' => 'pending_payment',
                'lock_version' => $session->lock_version,
                'amount_due_minor' => $session->checkout_amount_due_minor,
                'currency' => data_get($session->checkout_snapshot_json, 'currency'),
                'verification_method' => $session->checkout_verification_method,
                'guardian_id' => $session->checkout_guardian_id,
            ], JSON_THROW_ON_ERROR),
            'request_id' => $this->requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }
}
