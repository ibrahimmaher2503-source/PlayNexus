<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketScan;
use App\Models\TicketType;
use App\Models\User;
use App\Policies\TicketPolicy;
use App\Support\PhoneNormalizer;
use App\Support\TicketEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

class TicketController extends Controller
{
    private const ASSIGNMENT_REASONS = ['guardian_request', 'staff_correction'];

    private const CANCELLATION_REASONS = ['customer_request', 'duplicate_issue', 'staff_error'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $policy = app(TicketPolicy::class);
        $branches = $actor->accessibleBranches()
            ->where('branches.tenant_id', $tenant->getKey())
            ->orderBy('branches.name')
            ->orderBy('branches.id')
            ->get()
            ->filter(fn (Branch $branch): bool => $policy->issue($actor, $branch))
            ->values();
        $selectedBranchId = $this->selectedBranchId($request, $branches);
        $branchIds = $branches->modelKeys();
        $familyQuery = Validator::make($request->query(), ['family_q' => ['nullable', 'string', 'max:100']])->validate();
        $familySearch = trim($familyQuery['family_q'] ?? '');

        $types = TicketType::query()
            ->with(['branch:id,name,timezone', 'pricingRule:id,name,version,status'])
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereIn('branch_id', $branchIds)
            ->when($selectedBranchId, fn (Builder $query) => $query->where('branch_id', $selectedBranchId))
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();
        $rules = PricingRule::query()
            ->with('branch:id,name')
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereIn('branch_id', $branchIds)
            ->when($selectedBranchId, fn (Builder $query) => $query->where('branch_id', $selectedBranchId))
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();
        $tickets = Ticket::query()
            ->with(['branch:id,name,timezone', 'type:id,name,code', 'guardian:id,full_name', 'child:id,full_name'])
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('branch_id', $branchIds)
            ->when($selectedBranchId, fn (Builder $query) => $query->where('branch_id', $selectedBranchId))
            ->when($familySearch !== '', function (Builder $query) use ($familySearch): void {
                $query->where(function (Builder $query) use ($familySearch): void {
                    $query->whereHas('guardian', function (Builder $guardian) use ($familySearch): void {
                        $guardian->where('status', 'active')->where(function (Builder $guardian) use ($familySearch): void {
                            $guardian->where('full_name', 'like', '%'.$familySearch.'%');
                            if ($phone = PhoneNormalizer::normalize($familySearch)) {
                                $guardian->orWhere('phone_e164', $phone);
                            }
                        });
                    })->orWhereHas('child', fn (Builder $child) => $child->where('status', 'active')->where('full_name', 'like', '%'.$familySearch.'%'));
                });
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(75)
            ->get();

        $assignments = $this->activeFamilyAssignments($tenant, $familySearch);
        $scansByTicketId = TicketScan::query()->where('tenant_id', $tenant->getKey())
            ->whereIn('ticket_id', $tickets->modelKeys())->whereIn('branch_id', $branchIds)
            ->orderByDesc('scanned_at')->orderByDesc('id')->limit(300)->get()->groupBy('ticket_id');
        $manageableBranchIds = $branches
            ->filter(fn (Branch $branch): bool => $policy->createType($actor, $branch))
            ->modelKeys();
        $qrPayload = $request->session()->get('ticket_qr_payload');
        $qrTicket = $this->flashedTicket($request, $tenant, $actor);
        $scanResult = $request->session()->get('ticket_scan_result');
        $scanTicket = $this->flashedScanTicket($request, $tenant, $actor);

        return view('tickets.index', [
            'actor' => $actor,
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'types' => $types,
            'rules' => $rules,
            'tickets' => $tickets,
            'assignments' => $assignments,
            'familySearch' => $familySearch,
            'scansByTicketId' => $scansByTicketId,
            'manageableBranchIds' => $manageableBranchIds,
            'issueKey' => (string) Str::uuid(),
            'scanKey' => (string) Str::uuid(),
            'qrPayload' => $qrTicket && is_string($qrPayload) ? $qrPayload : null,
            'qrTicket' => $qrTicket,
            'scanResult' => is_string($scanResult) ? $scanResult : null,
            'scanTicket' => $scanTicket,
        ]);
    }

    public function storeType(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $this->trim($request, ['code', 'name']);
        if (is_string($request->input('code'))) {
            $request->merge(['code' => Str::upper($request->input('code'))]);
        }
        $data = Validator::make($request->all(), [
            'branch_id' => ['required', 'integer'],
            'pricing_rule_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:190'],
        ])->validate();

        $result = DB::transaction(function () use ($request, $actor, $tenant, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $branch = $this->lockedBranch($lockedTenant, $lockedActor, (int) $data['branch_id']);
            Gate::forUser($lockedActor)->authorize('createType', [Ticket::class, $branch]);
            $rule = PricingRule::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('branch_id', $branch->getKey())
                ->where('status', 'active')
                ->whereKey($data['pricing_rule_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $existing = TicketType::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('branch_id', $branch->getKey())
                ->where('code', $data['code'])
                ->first();
            if ($existing) {
                return ['conflict' => true];
            }

            $type = TicketType::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $branch->getKey(),
                'pricing_rule_id' => $rule->getKey(),
                'code' => $data['code'],
                'name' => $data['name'],
                'price_minor' => $rule->base_price_minor,
                'currency' => $rule->currency,
                'max_uses' => 1,
                'status' => 'active',
                'created_by_user_id' => $lockedActor->getKey(),
            ]);
            $this->audit($request, $lockedActor, $lockedTenant, $branch, 'ticket.type.created', 'ticket_type', $type->getKey(), 'setup_change', [
                'ticket_type_id' => (string) $type->getKey(),
                'pricing_rule_id' => (string) $rule->getKey(),
            ]);

            return ['type' => $type];
        });

        if (isset($result['conflict'])) {
            if (! $request->expectsJson()) {
                return to_route('pricing.index', ['tab' => 'types', 'branch_id' => $data['branch_id']])
                    ->withErrors(['code' => __('tickets.errors.type_conflict')])
                    ->withInput();
            }

            return $this->conflictResponse($request, __('tickets.errors.type_conflict'));
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('tickets.type_created'), 'ticket_type_id' => $result['type']->getKey()], 201)
            : to_route('pricing.index', ['tab' => 'types', 'branch_id' => $data['branch_id']])->with('success', __('tickets.type_created'));
    }

    public function issue(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $data = $this->validateIssue($request);
        $fingerprint = $this->fingerprint($data, ['branch_id', 'ticket_type_id', 'guardian_id', 'child_id', 'service_date']);

        $result = DB::transaction(function () use ($request, $actor, $tenant, $data, $fingerprint): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $branch = $this->lockedBranch($lockedTenant, $lockedActor, (int) $data['branch_id']);
            Gate::forUser($lockedActor)->authorize('issue', [Ticket::class, $branch]);

            $existing = Ticket::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('issued_by_user_id', $lockedActor->getKey())
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                if (! hash_equals($existing->issue_fingerprint, $fingerprint)) {
                    throw new HttpException(409, __('tickets.errors.idempotency_conflict'));
                }

                return ['ticket' => $existing, 'created' => false];
            }

            $type = TicketType::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('branch_id', $branch->getKey())
                ->where('status', 'active')
                ->whereKey($data['ticket_type_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $rule = PricingRule::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('branch_id', $branch->getKey())
                ->whereKey($type->pricing_rule_id)
                ->lockForUpdate()
                ->first();
            if (! $rule) {
                throw ValidationException::withMessages(['ticket_type_id' => __('tickets.errors.type_unavailable')]);
            }
            $this->ensureActiveAssignment($lockedTenant, (int) $data['guardian_id'], (int) $data['child_id']);
            [$validFrom, $validUntil] = $this->serviceWindow($lockedTenant, $branch, $data['service_date']);
            $payload = 'pnx_'.Str::random(48);
            $displayCode = $this->uniqueDisplayCode($lockedTenant);
            $now = now('UTC');
            $ticket = Ticket::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $branch->getKey(),
                'ticket_type_id' => $type->getKey(),
                'guardian_id' => $data['guardian_id'],
                'child_id' => $data['child_id'],
                'service_date' => $data['service_date'],
                'status' => 'issued',
                'code_hash' => hash('sha256', $payload),
                'code_payload_encrypted' => $payload,
                'display_code' => $displayCode,
                'price_minor' => $type->price_minor,
                'currency' => $type->currency,
                'price_snapshot_json' => [
                    'ticket_type_id' => (int) $type->getKey(),
                    'pricing_rule_id' => (int) $rule->getKey(),
                    'pricing_rule_version' => (int) $rule->version,
                    'base_duration_seconds' => (int) $rule->base_duration_seconds,
                    'grace_period_seconds' => (int) $rule->grace_period_seconds,
                    'overtime_unit_seconds' => (int) $rule->overtime_unit_seconds,
                    'overtime_price_minor' => (int) $rule->overtime_price_minor,
                    'price_minor' => (int) $type->price_minor,
                    'currency' => $type->currency,
                    'tax_rate_bps' => (int) $rule->tax_rate_bps,
                    'tax_mode' => $rule->tax_mode,
                    'branch_timezone' => $branch->timezone,
                ],
                'issued_at' => $now,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'uses_count' => 0,
                'max_uses' => 1,
                'idempotency_key' => $data['idempotency_key'],
                'issue_fingerprint' => $fingerprint,
                'issued_by_user_id' => $lockedActor->getKey(),
                'lock_version' => 1,
            ]);
            $this->audit($request, $lockedActor, $lockedTenant, $branch, 'ticket.issued', 'ticket', $ticket->getKey(), 'ticket_issue', [
                'ticket_id' => (string) $ticket->getKey(),
                'ticket_type_id' => (string) $type->getKey(),
                'guardian_id' => (string) $data['guardian_id'],
                'child_id' => (string) $data['child_id'],
                'service_date' => $data['service_date'],
            ]);

            return ['ticket' => $ticket, 'created' => true];
        });

        return $this->issuedResponse($request, $result['ticket'], $result['created']);
    }

    public function scan(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $this->trim($request, ['code']);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $data = Validator::make($request->all(), [
            'branch_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'min:6', 'max:512'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $lookup = str_starts_with(Str::upper($data['code']), 'PN-') ? Str::upper($data['code']) : $data['code'];
        $inputHash = hash('sha256', $lookup);
        $fingerprint = $this->fingerprint([
            'branch_id' => (int) $data['branch_id'],
            'code_hash' => $inputHash,
            'scan_purpose' => 'validate',
        ], ['branch_id', 'code_hash', 'scan_purpose']);

        $result = DB::transaction(function () use ($request, $actor, $tenant, $data, $lookup, $inputHash, $fingerprint): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $branch = $this->lockedBranch($lockedTenant, $lockedActor, (int) $data['branch_id']);
            Gate::forUser($lockedActor)->authorize('scan', [Ticket::class, $branch]);
            $existing = TicketScan::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('scanned_by_user_id', $lockedActor->getKey())
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw new HttpException(409, __('tickets.errors.idempotency_conflict'));
                }

                $replayedTicket = $existing->ticket;

                return ['scan' => $existing, 'ticket' => $replayedTicket && (int) $replayedTicket->branch_id === (int) $branch->getKey() ? $replayedTicket : null];
            }

            $ticket = Ticket::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where(function (Builder $query) use ($lookup, $inputHash): void {
                    $query->where('code_hash', $inputHash)
                        ->orWhere('display_code', Str::upper($lookup));
                })
                ->lockForUpdate()
                ->first();
            $resultCode = $this->scanResult($ticket, $branch);
            if ($ticket && $resultCode === 'accepted' && $ticket->assignment_locked_at === null) {
                $ticket->forceFill([
                    'assignment_locked_at' => now('UTC'),
                    'lock_version' => (int) $ticket->lock_version + 1,
                ])->save();
                $this->audit($request, $lockedActor, $lockedTenant, $branch, 'ticket.assignment.locked', 'ticket', $ticket->getKey(), 'first_accepted_scan', [
                    'ticket_id' => (string) $ticket->getKey(),
                    'guardian_id' => (string) $ticket->guardian_id,
                    'child_id' => (string) $ticket->child_id,
                ]);
            }

            $scan = TicketScan::query()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'ticket_id' => $ticket?->getKey(),
                'branch_id' => $branch->getKey(),
                'scanned_by_user_id' => $lockedActor->getKey(),
                'scanned_at' => now('UTC'),
                'scan_purpose' => 'validate',
                'result' => $resultCode,
                'code_hash' => $ticket?->code_hash ?? $inputHash,
                'idempotency_key' => $data['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'request_id' => $this->requestId($request),
            ]);

            return ['scan' => $scan, 'ticket' => $ticket && $resultCode !== 'wrong_branch' ? $ticket->fresh() : null];
        });

        return $this->scanResponse($request, $result['scan'], $result['ticket']);
    }

    public function reassign(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $ticket = $this->ticketInTenant($ticket, $tenant, $actor);
        Gate::forUser($actor)->authorize('reassign', $ticket);
        $this->parseFamilyAssignment($request);
        $data = Validator::make($request->all(), [
            'guardian_id' => ['required', 'integer'],
            'child_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(self::ASSIGNMENT_REASONS)],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();

        $result = DB::transaction(function () use ($request, $actor, $tenant, $ticket, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $locked = $this->lockedTicket($ticket, $lockedTenant, $lockedActor);
            Gate::forUser($lockedActor)->authorize('reassign', $locked);
            if ((int) $locked->lock_version !== (int) $data['expected_version']) {
                return ['conflict' => true];
            }
            if ($locked->status !== 'issued' || $locked->assignment_locked_at !== null || $locked->consumed_at !== null || (int) $locked->uses_count > 0) {
                return ['locked' => true];
            }
            $this->ensureActiveAssignment($lockedTenant, (int) $data['guardian_id'], (int) $data['child_id']);
            $before = ['guardian_id' => (string) $locked->guardian_id, 'child_id' => (string) $locked->child_id];
            $locked->forceFill([
                'guardian_id' => $data['guardian_id'],
                'child_id' => $data['child_id'],
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
            $this->audit($request, $lockedActor, $lockedTenant, $locked->branch, 'ticket.assignment.changed', 'ticket', $locked->getKey(), 'ticket_assignment_'.$data['reason'], [
                'ticket_id' => (string) $locked->getKey(),
                'guardian_id' => (string) $locked->guardian_id,
                'child_id' => (string) $locked->child_id,
            ], $before);

            return ['ticket' => $locked];
        });

        if (isset($result['conflict'])) {
            return $this->conflictResponse($request, __('tickets.errors.version_conflict'));
        }
        if (isset($result['locked'])) {
            return $this->conflictResponse($request, __('tickets.errors.assignment_locked'));
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('tickets.assignment_updated'), 'lock_version' => $result['ticket']->lock_version])
            : back()->with('success', __('tickets.assignment_updated'));
    }

    public function cancel(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $ticket = $this->ticketInTenant($ticket, $tenant, $actor);
        Gate::forUser($actor)->authorize('cancel', $ticket);
        $data = Validator::make($request->all(), [
            'reason' => ['required', Rule::in(self::CANCELLATION_REASONS)],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();

        $result = DB::transaction(function () use ($request, $actor, $tenant, $ticket, $data): array {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $locked = $this->lockedTicket($ticket, $lockedTenant, $lockedActor);
            Gate::forUser($lockedActor)->authorize('cancel', $locked);
            if ((int) $locked->lock_version !== (int) $data['expected_version']) {
                return ['conflict' => true];
            }
            if ($locked->status !== 'issued' || $locked->assignment_locked_at !== null || $locked->consumed_at !== null || (int) $locked->uses_count > 0) {
                return ['ineligible' => true];
            }
            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now('UTC'),
                'cancelled_by_user_id' => $lockedActor->getKey(),
                'cancellation_reason' => $data['reason'],
                'lock_version' => (int) $locked->lock_version + 1,
            ])->save();
            $this->audit($request, $lockedActor, $lockedTenant, $locked->branch, 'ticket.cancelled', 'ticket', $locked->getKey(), 'ticket_cancel_'.$data['reason'], [
                'ticket_id' => (string) $locked->getKey(),
                'status' => 'cancelled',
            ], ['status' => 'issued']);

            return ['ticket' => $locked];
        });

        if (isset($result['conflict'])) {
            return $this->conflictResponse($request, __('tickets.errors.version_conflict'));
        }
        if (isset($result['ineligible'])) {
            return $this->conflictResponse($request, __('tickets.errors.cancel_ineligible'));
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('tickets.cancelled'), 'status' => 'cancelled', 'financial_refund_processed' => false])
            : back()->with('success', __('tickets.cancelled'));
    }

    public function reprint(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $ticket = $this->ticketInTenant($ticket, $tenant, $actor);
        Gate::forUser($actor)->authorize('reprint', $ticket);
        $data = Validator::make($request->all(), [
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();
        $ticket = DB::transaction(function () use ($request, $actor, $tenant, $ticket, $data): Ticket {
            [$lockedActor, $lockedTenant] = $this->lockedContext($actor, $tenant);
            $locked = $this->lockedTicket($ticket, $lockedTenant, $lockedActor);
            Gate::forUser($lockedActor)->authorize('reprint', $locked);
            if ((int) $locked->lock_version !== (int) $data['expected_version']) {
                throw new HttpException(409, __('tickets.errors.version_conflict'));
            }
            $this->audit($request, $lockedActor, $lockedTenant, $locked->branch, 'ticket.reprinted', 'ticket', $locked->getKey(), 'ticket_reprint', [
                'ticket_id' => (string) $locked->getKey(),
            ]);

            return $locked;
        });

        return $request->expectsJson()
            ? response()->json(['message' => __('tickets.reprinted'), 'ticket_id' => $ticket->getKey(), 'qr_payload' => $ticket->code_payload_encrypted])->header('Cache-Control', 'no-store, private')
            : back()->with('success', __('tickets.reprinted'))
                ->with('ticket_qr_payload', $ticket->code_payload_encrypted)
                ->with('ticket_qr_id', $ticket->getKey());
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', Ticket::class);

        return [$actor, $tenant];
    }

    /** @return array{User, Tenant} */
    private function lockedContext(User $actor, Tenant $tenant): array
    {
        // ponytail: tenant lock serializes MVP commands; use ordered branch/family locks if reception throughput requires it.
        $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
        $lockedActor = User::query()->whereKey($actor->getKey())->where('tenant_id', $lockedTenant->getKey())->where('status', 'active')->lockForUpdate()->firstOrFail();
        Gate::forUser($lockedActor)->authorize('viewAny', Ticket::class);

        return [$lockedActor, $lockedTenant];
    }

    private function lockedBranch(Tenant $tenant, User $actor, int $branchId): Branch
    {
        return $actor->accessibleBranches()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->whereKey($branchId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ticketInTenant(Ticket $ticket, Tenant $tenant, User $actor): Ticket
    {
        return Ticket::query()->where('tenant_id', $tenant->getKey())
            ->whereIn('branch_id', $actor->accessibleBranches()->select('branches.id'))
            ->whereKey($ticket->getKey())->firstOrFail();
    }

    private function lockedTicket(Ticket $ticket, Tenant $tenant, User $actor): Ticket
    {
        $branch = $this->lockedBranch($tenant, $actor, (int) $ticket->branch_id);
        $locked = Ticket::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($ticket->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $locked->setRelation('branch', $branch);

        return $locked;
    }

    /** @param Collection<int, Branch> $branches */
    private function selectedBranchId(Request $request, Collection $branches): ?int
    {
        $selected = $request->query('branch_id', $request->session()->get('branch_id'));
        if ($request->has('branch_id') && $selected === '') {
            return null;
        }
        if ($request->has('branch_id')) {
            abort_unless((is_int($selected) || is_string($selected)) && ctype_digit((string) $selected) && $branches->contains('id', (int) $selected), 404);
        }
        if (! is_string($selected) && ! is_int($selected)) {
            return null;
        }

        return ctype_digit((string) $selected) && $branches->contains('id', (int) $selected) ? (int) $selected : null;
    }

    private function activeFamilyQuery(Tenant $tenant): QueryBuilder
    {
        return TicketEligibility::familyQuery($tenant);
    }

    private function activeFamilyAssignments(Tenant $tenant, string $search): Collection
    {
        return $this->activeFamilyQuery($tenant)
            ->when($search !== '', function (QueryBuilder $query) use ($search): void {
                $query->where(function (QueryBuilder $query) use ($search): void {
                    $query->where('guardians.full_name', 'like', '%'.$search.'%')
                        ->orWhere('children.full_name', 'like', '%'.$search.'%');
                    if ($phone = PhoneNormalizer::normalize($search)) {
                        $query->orWhere('guardians.phone_e164', $phone);
                    }
                });
            })
            ->orderBy('children.full_name')
            ->orderBy('guardians.full_name')
            ->limit(100)
            ->get([
                'guardian_child.guardian_id',
                'guardian_child.child_id',
                'guardians.full_name as guardian_name',
                'guardians.phone_e164',
                'children.full_name as child_name',
            ])
            ->map(fn (object $row): object => (object) [
                'guardian_id' => $row->guardian_id,
                'child_id' => $row->child_id,
                'guardian_name' => $row->guardian_name,
                'child_name' => $row->child_name,
                'phone_masked' => PhoneNormalizer::mask($row->phone_e164),
            ]);
    }

    private function flashedTicket(Request $request, Tenant $tenant, User $actor): ?Ticket
    {
        $id = $request->session()->get('ticket_qr_id');
        if (! is_numeric($id)) {
            return null;
        }
        $ticket = Ticket::query()->with(['branch:id,name,timezone', 'type:id,name', 'guardian:id,full_name', 'child:id,full_name'])
            ->where('tenant_id', $tenant->getKey())
            ->whereKey((int) $id)
            ->first();

        return $ticket && Gate::forUser($actor)->allows('view', $ticket) ? $ticket : null;
    }

    private function flashedScanTicket(Request $request, Tenant $tenant, User $actor): ?Ticket
    {
        $id = $request->session()->get('ticket_scan_id');
        if (! is_numeric($id)) {
            return null;
        }
        $ticket = Ticket::query()->with(['branch:id,name', 'type:id,name', 'child:id,full_name'])
            ->where('tenant_id', $tenant->getKey())
            ->whereKey((int) $id)
            ->first();

        return $ticket && Gate::forUser($actor)->allows('view', $ticket) ? $ticket : null;
    }

    /** @return array<string, mixed> */
    private function validateIssue(Request $request): array
    {
        $this->parseFamilyAssignment($request);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);

        return Validator::make($request->all(), [
            'branch_id' => ['required', 'integer'],
            'ticket_type_id' => ['required', 'integer'],
            'guardian_id' => ['required', 'integer'],
            'child_id' => ['required', 'integer'],
            'service_date' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
    }

    private function ensureActiveAssignment(Tenant $tenant, int $guardianId, int $childId): void
    {
        $exists = $this->activeFamilyQuery($tenant)
            ->where('guardian_child.guardian_id', $guardianId)
            ->where('guardian_child.child_id', $childId)
            ->lockForUpdate()->first(['guardian_child.guardian_id']);

        if (! $exists) {
            throw ValidationException::withMessages(['child_id' => __('tickets.errors.family_unavailable')]);
        }
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function serviceWindow(Tenant $tenant, Branch $branch, string $serviceDate): array
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $serviceDate, $branch->timezone);
        if (! $date || $date->format('Y-m-d') !== $serviceDate || $date->lessThan(CarbonImmutable::now($branch->timezone)->startOfDay())) {
            throw ValidationException::withMessages(['service_date' => __('tickets.errors.service_date_invalid')]);
        }
        $hours = DB::table('branch_opening_hours')
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('weekday', $date->isoWeekday())
            ->lockForUpdate()
            ->first();
        if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            throw ValidationException::withMessages(['service_date' => __('tickets.errors.branch_closed')]);
        }
        $from = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $serviceDate.' '.$hours->opens_at, $branch->timezone);
        $until = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $serviceDate.' '.$hours->closes_at, $branch->timezone);
        if (! $from || ! $until || ! $until->greaterThan($from)) {
            throw ValidationException::withMessages(['service_date' => __('tickets.errors.branch_hours_invalid')]);
        }
        if ($until->lessThanOrEqualTo(CarbonImmutable::now($branch->timezone))) {
            throw ValidationException::withMessages(['service_date' => __('tickets.errors.service_date_invalid')]);
        }

        return [$from->utc(), $until->utc()];
    }

    private function scanResult(?Ticket $ticket, Branch $branch): string
    {
        return TicketEligibility::result($ticket, $branch);
    }

    private function uniqueDisplayCode(Tenant $tenant): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = 'PN-';
            for ($index = 0; $index < 8; $index++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            if (! Ticket::query()->where('tenant_id', $tenant->getKey())->where('display_code', $code)->exists()) {
                return $code;
            }
        }

        throw new HttpException(503, __('tickets.errors.code_unavailable'));
    }

    /** @param array<string, mixed> $data @param list<string> $keys */
    private function fingerprint(array $data, array $keys): string
    {
        $canonical = [];
        foreach ($keys as $key) {
            $canonical[$key] = str_ends_with($key, '_id') ? (int) $data[$key] : (string) $data[$key];
        }

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    private function issuedResponse(Request $request, Ticket $ticket, bool $created): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __($created ? 'tickets.issued' : 'tickets.issue_replayed'),
                'created' => $created,
                'ticket_id' => $ticket->getKey(),
                'display_code' => $ticket->display_code,
                'qr_payload' => $ticket->code_payload_encrypted,
                'service_date' => $ticket->service_date->format('Y-m-d'),
                'valid_from' => $ticket->valid_from->toIso8601String(),
                'valid_until' => $ticket->valid_until->toIso8601String(),
                'lock_version' => (int) $ticket->lock_version,
            ], $created ? 201 : 200)->header('Cache-Control', 'no-store, private');
        }

        return to_route('tickets.index', ['branch_id' => $ticket->branch_id])
            ->with('success', __($created ? 'tickets.issued' : 'tickets.issue_replayed'))
            ->with('ticket_qr_payload', $ticket->code_payload_encrypted)
            ->with('ticket_qr_id', $ticket->getKey());
    }

    private function scanResponse(Request $request, TicketScan $scan, ?Ticket $ticket): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'accepted' => $scan->result === 'accepted',
                'result' => $scan->result,
                'ticket_id' => $ticket?->getKey(),
                'assignment_locked' => $ticket !== null && $ticket->assignment_locked_at !== null,
                'scanned_at' => $scan->scanned_at->toIso8601String(),
            ]);
        }

        return to_route('tickets.index', ['branch_id' => $scan->branch_id])
            ->with('ticket_scan_result', $scan->result)
            ->with('ticket_scan_id', $ticket?->getKey());
    }

    private function conflictResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 409)
            : back()->withErrors(['ticket' => $message])->withInput();
    }

    private function audit(Request $request, User $actor, Tenant $tenant, Branch $branch, string $action, string $subjectType, int|string $subjectId, string $reasonCode, array $after, ?array $before = null): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(),
            'branch_id' => $branch->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_type' => 'user',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectId,
            'outcome' => 'success',
            'reason_code' => $reasonCode,
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => $this->requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', Str::uuid());
    }

    /** @param list<string> $fields */
    private function trim(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }
    }

    private function parseFamilyAssignment(Request $request): void
    {
        if (! $request->has('family_assignment')) {
            return;
        }
        $assignment = $request->input('family_assignment');
        if (! is_string($assignment) || preg_match('/^([1-9]\d*):([1-9]\d*)$/', $assignment, $ids) !== 1) {
            throw ValidationException::withMessages(['family_assignment' => __('tickets.errors.family_unavailable')]);
        }
        $request->merge(['guardian_id' => $ids[1], 'child_id' => $ids[2]]);
    }
}
