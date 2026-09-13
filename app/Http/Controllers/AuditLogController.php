<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;

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
    ];

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
        [$actor, $tenant] = $this->authorizedContext($request);
        $validator = Validator::make($request->query(), [
            'action' => ['nullable', 'string', Rule::in(self::ACTIONS)],
            'outcome' => ['nullable', 'string', Rule::in(['success'])],
            'actor_user_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
        ], [
            'action.in' => __('audit.errors.action_invalid'),
            'outcome.in' => __('audit.errors.outcome_invalid'),
            'actor_user_id.integer' => __('audit.errors.actor_invalid'),
            'actor_user_id.min' => __('audit.errors.actor_invalid'),
            'actor_user_id.exists' => __('audit.errors.actor_invalid'),
        ]);

        $filters = $validator->validate();
        $query = DB::table('audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('outcome', 'success')
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

        if (isset($filters['action']) && $filters['action'] !== null) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['outcome']) && $filters['outcome'] !== null) {
            $query->where('outcome', $filters['outcome']);
        }

        if (isset($filters['actor_user_id']) && $filters['actor_user_id'] !== null) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }

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

        $beforeSnapshots = $logs->getCollection()->mapWithKeys(fn (object $log): array => [$log->id => $this->compactSnapshot($log->before_json)]);
        $afterSnapshots = $logs->getCollection()->mapWithKeys(fn (object $log): array => [$log->id => $this->compactSnapshot($log->after_json)]);

        return view('audit.index', compact('actor', 'tenant', 'logs', 'filters', 'actorLabels', 'branchLabels', 'beforeSnapshots', 'afterSnapshots'));
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->firstOrFail();

        Gate::forUser($actor)->authorize('view', $tenant);

        return [$actor, $tenant];
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
