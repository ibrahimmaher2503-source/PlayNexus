<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const ACTIONS = [
        'staff.invited',
        'staff.created',
        'staff.status.changed',
        'staff.branch_assignment.changed',
        'custom_role.created',
        'custom_role.updated',
        'branch.created',
        'branch.status.changed',
        'branch.settings.updated',
        'tenant.profile.updated',
        'family.created',
        'family.guardian.updated',
        'family.child.updated',
        'family.child.added',
        'pricing.rule.created',
        'pricing.rule.versioned',
        'ticket.type.created',
        'ticket.issued',
        'ticket.assignment.locked',
        'ticket.assignment.changed',
        'ticket.cancelled',
        'ticket.reprinted',
        'session.checked_in',
        'session.extended',
        'session.adjusted',
        'session.cancelled',
        'session.checkout_prepared',
        'session.cash_settled',
        'order.created',
        'order.cash_paid',
        'order.discount_requested',
        'order.discount_approved',
        'order.discount_rejected',
        'order.discount_consumed',
        'order.refund_requested',
        'order.refund_approved',
        'order.refund_executed',
        'ticket.issued_from_order',
    ];

    private const SUBJECT_TYPES = ['user', 'tenant', 'branch', 'guardian', 'child', 'pricing_rule', 'ticket', 'ticket_type', 'play_session', 'order', 'payment', 'refund', 'product', 'approval_record'];

    private const SNAPSHOT_KEYS = [
        'status',
        'role',
        'is_active',
        'branch_id',
        'name',
        'code',
        'guardian_id',
        'child_id',
        'pricing_rule_id',
        'old_pricing_rule_id',
        'new_pricing_rule_id',
        'old_version',
        'new_version',
        'ticket_type_id',
        'ticket_id',
        'service_date',
    ];

    public function index(Request $request): View
    {
        [$actor, $tenant, $branchIds, $selfOnly, $owner] = $this->authorizedContext($request);
        $canExport = ! $selfOnly;
        $validator = Validator::make($request->query(), [
            'action' => ['nullable', 'string', Rule::in(self::ACTIONS)],
            'outcome' => ['nullable', 'string', Rule::in(['success'])],
            'actor_user_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)->whereIn('id', $branchIds)),
            ],
            'subject_type' => ['nullable', 'string', Rule::in(self::SUBJECT_TYPES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ], [
            'action.in' => __('audit.errors.action_invalid'),
            'outcome.in' => __('audit.errors.outcome_invalid'),
            'actor_user_id.integer' => __('audit.errors.actor_invalid'),
            'actor_user_id.min' => __('audit.errors.actor_invalid'),
            'actor_user_id.exists' => __('audit.errors.actor_invalid'),
            'branch_id.exists' => __('audit.errors.branch_invalid'),
            'subject_type.in' => __('audit.errors.subject_invalid'),
            'date_from.date_format' => __('audit.errors.date_invalid'),
            'date_to.date_format' => __('audit.errors.date_invalid'),
            'date_to.after_or_equal' => __('audit.errors.date_order_invalid'),
        ]);

        $filters = $validator->validate();
        $this->assertRange($filters, $tenant->timezone ?: 'UTC');
        $query = $this->filteredQuery($tenant, $branchIds, $selfOnly ? $actor->id : null, $owner, $filters)
            ->select([
                'id',
                'branch_id',
                'actor_user_id',
                'action',
                'subject_type',
                'subject_id',
                'outcome',
                'reason_code',
                'before_json',
                'after_json',
                'request_id',
                'occurred_at',
            ]);

        $logs = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $actorLabels = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $logs->getCollection()->pluck('actor_user_id')->unique()->values())
            ->pluck('name', 'id');
        $branchLabels = Branch::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $logs->getCollection()->pluck('branch_id')->filter()->unique()->values())
            ->pluck('name', 'id');
        $filterActors = User::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']);
        $filterBranches = Branch::query()->where('tenant_id', $tenant->id)->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name']);

        $beforeSnapshots = $logs->getCollection()->mapWithKeys(fn (object $log): array => [$log->id => $this->compactSnapshot($log->before_json)]);
        $afterSnapshots = $logs->getCollection()->mapWithKeys(fn (object $log): array => [$log->id => $this->compactSnapshot($log->after_json)]);

        return view('audit.index', compact('actor', 'tenant', 'logs', 'filters', 'actorLabels', 'branchLabels', 'filterActors', 'filterBranches', 'beforeSnapshots', 'afterSnapshots', 'canExport'));
    }

    public function export(Request $request): StreamedResponse
    {
        [$actor, $tenant, $branchIds, $selfOnly, $owner] = $this->authorizedContext($request);
        abort_if($selfOnly, 403);
        $filters = Validator::make($request->query(), [
            'action' => ['nullable', Rule::in(self::ACTIONS)], 'outcome' => ['nullable', Rule::in(['success'])],
            'actor_user_id' => ['nullable', 'integer', 'min:1'], 'branch_id' => ['nullable', 'integer', 'min:1'],
            'subject_type' => ['nullable', Rule::in(self::SUBJECT_TYPES)], 'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ])->validate();
        if (isset($filters['branch_id']) && ! $branchIds->contains((int) $filters['branch_id'])) {
            abort(404);
        }
        $this->assertRange($filters, $tenant->timezone ?: 'UTC');
        $query = $this->filteredQuery($tenant, $branchIds, null, $owner, $filters)->orderBy('occurred_at')->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['id', 'branch_id', 'actor_user_id', 'action', 'subject_type', 'subject_id', 'outcome', 'reason_code', 'request_id', 'occurred_at_utc']);
            $query->chunk(500, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [$log->id, $log->branch_id, $log->actor_user_id, $log->action, $log->subject_type, $log->subject_id, $log->outcome, $log->reason_code, $log->request_id, $log->occurred_at]);
                }
            });
            fclose($out);
        }, 'audit-log.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{User, Tenant, Collection<int, int>, bool, bool} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->whereKey($request->user()->getAuthIdentifier())->where('status', 'active')->firstOrFail();
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        $owner = Gate::forUser($actor)->allows('view', $tenant);
        $assignments = DB::table('branch_user')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)->where('is_active', true);
        $managerIds = (clone $assignments)->where('role', 'branch_manager')->pluck('branch_id');
        $branchIds = $owner ? Branch::query()->where('tenant_id', $tenant->id)->pluck('id') : ($managerIds->isNotEmpty() ? $managerIds : $assignments->pluck('branch_id'));
        abort_if($branchIds->isEmpty() && ! $owner, 403);

        return [$actor, $tenant, $branchIds->map(fn ($id) => (int) $id)->values(), ! $owner && $managerIds->isEmpty(), $owner];
    }

    private function filteredQuery(Tenant $tenant, $branchIds, ?int $actorOnly, bool $owner, array $filters)
    {
        $query = DB::table('audit_logs')->where('tenant_id', $tenant->id)->where('outcome', 'success');
        if (! $owner) {
            $query->whereIn('branch_id', $branchIds);
        }
        if ($actorOnly !== null) {
            $query->where('actor_user_id', $actorOnly);
        }
        foreach (['action', 'outcome', 'actor_user_id', 'branch_id', 'subject_type'] as $key) {
            if (filled($filters[$key] ?? null)) {
                $query->where($key, $filters[$key]);
            }
        }
        $timezone = $tenant->timezone ?: 'UTC';
        if (filled($filters['date_from'] ?? null)) {
            $query->where('occurred_at', '>=', Carbon::createFromFormat('!Y-m-d', $filters['date_from'], $timezone)->utc());
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->where('occurred_at', '<', Carbon::createFromFormat('!Y-m-d', $filters['date_to'], $timezone)->addDay()->utc());
        }

        return $query;
    }

    private function assertRange(array $filters, string $timezone): void
    {
        if (filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)
            && Carbon::createFromFormat('!Y-m-d', $filters['date_from'], $timezone)->diffInDays(Carbon::createFromFormat('!Y-m-d', $filters['date_to'], $timezone)) > 30) {
            throw ValidationException::withMessages(['date_to' => __('audit.errors.range_too_large')]);
        }
    }

    /** @return array<string, scalar> */
    private function compactSnapshot(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        try {
            $snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($snapshot)) {
            return [];
        }

        $compact = [];
        foreach (self::SNAPSHOT_KEYS as $key) {
            if (array_key_exists($key, $snapshot) && is_scalar($snapshot[$key])) {
                $compact[$key] = $snapshot[$key];
            }
        }

        return $compact;
    }
}
