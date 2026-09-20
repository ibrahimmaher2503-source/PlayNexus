<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const TYPES = ['revenue', 'attendance', 'sessions', 'staff'];

    public function index(Request $request, string $type = 'revenue')
    {
        [$actor, $tenant, $branches, $owner, $manager] = $this->context($request, $type);
        [$filters, $selected, $from, $to] = $this->filters($request, $branches);
        [$query, $summary, $extras] = $this->query($type, $tenant, $actor, $selected, $from, $to, $filters, $owner);
        $rows = $query->paginate(25)->withQueryString();
        $availableTypes = $owner || $manager ? self::TYPES : ['attendance', 'sessions', 'staff'];
        $summaryByCurrency = $extras['summary_by_currency'] ?? [];
        $breakdowns = $extras['breakdowns'] ?? [];
        $timezones = $selected->pluck('timezone')->filter()->unique()->values();
        $generatedAt = $timezones->count() === 1
            ? now((string) $timezones->first())->format('Y-m-d H:i T')
            : __('reports.mixed_context');

        return view('reports.index', compact('actor', 'tenant', 'branches', 'selected', 'filters', 'type', 'summary', 'summaryByCurrency', 'breakdowns', 'generatedAt', 'rows', 'from', 'to', 'owner', 'manager', 'availableTypes'));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        [$actor, $tenant, $branches, $owner, $manager] = $this->context($request, $type);
        abort_unless($owner || $manager, 403);
        [$filters, $selected, $from, $to] = $this->filters($request, $branches);
        [$query] = $this->query($type, $tenant, $actor, $selected, $from, $to, $filters, $owner);

        return response()->streamDownload(function () use ($query, $type): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, $this->csvHeaders($type));
            $idColumn = match ($type) {
                'revenue' => 'orders.id',
                'attendance', 'sessions' => 'play_sessions.id',
                'staff' => 'audit_logs.id',
            };
            $write = function ($rows) use ($out, $type): void {
                foreach ($rows as $row) {
                    fputcsv($out, $this->csvRow($type, $row));
                }
            };
            $type === 'revenue'
                ? $query->chunk(500, $write)
                : $query->reorder()->chunkById(500, $write, $idColumn, 'id');
            fclose($out);
        }, $type.'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{User, Tenant, Collection<int, Branch>, bool, bool} */
    private function context(Request $request, string $type): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        $actor = User::query()->whereKey($request->user()->getAuthIdentifier())->where('status', 'active')->firstOrFail();
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        $owner = DB::table('tenant_owners')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)->exists();
        $roles = match ($type) {
            'revenue' => ['branch_manager'],
            'attendance', 'sessions' => ['branch_manager', 'reception_staff', 'reception', 'cashier'],
            'staff' => ['branch_manager', 'reception_staff', 'reception', 'cashier'],
        };
        $assigned = DB::table('branch_user')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)
            ->where('is_active', true)->whereIn('role', $roles)->pluck('branch_id');
        abort_unless($owner || $assigned->isNotEmpty(), 403);
        $branches = Branch::query()->where('tenant_id', $tenant->id)
            ->when(! $owner, fn ($query) => $query->whereIn('id', $assigned))
            ->orderBy('name')->get();
        abort_if($branches->isEmpty(), 403);
        $manager = $owner || DB::table('branch_user')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)
            ->where('is_active', true)->where('role', 'branch_manager')->exists();

        return [$actor, $tenant, $branches, $owner, $manager];
    }

    /** @return array{array<string, mixed>, Collection<int, Branch>, CarbonImmutable, CarbonImmutable} */
    private function filters(Request $request, Collection $branches): array
    {
        $filters = Validator::make($request->query(), [
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['active', 'pending_payment', 'completed', 'cancelled'])],
            'actor_user_id' => ['nullable', 'integer', 'min:1'],
            'child_id' => ['nullable', 'integer', 'min:1'],
            'ticket_id' => ['nullable', 'integer', 'min:1'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'amount_desc', 'amount_asc'])],
        ])->validate();
        $today = CarbonImmutable::now($branches->first()->timezone)->toDateString();
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'] ?? $today, 'UTC');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'] ?? $today, 'UTC');
        if ($from->diffInDays($to) > 30) {
            throw ValidationException::withMessages(['to' => __('reports.range_too_large')]);
        }
        $selected = $branches;
        if (isset($filters['branch_id'])) {
            $branch = $branches->firstWhere('id', (int) $filters['branch_id']);
            abort_unless($branch, 404);
            $selected = collect([$branch]);
        }

        return [$filters, $selected, $from, $to];
    }

    /** @return array{Builder, array<string, mixed>, array<string, mixed>} */
    private function query(string $type, Tenant $tenant, User $actor, Collection $branches, CarbonImmutable $from, CarbonImmutable $to, array $filters, bool $owner): array
    {
        return match ($type) {
            'revenue' => $this->revenue($tenant, $branches, $from, $to, $filters),
            'attendance' => $this->attendance($tenant, $branches, $from, $to, $filters),
            'sessions' => $this->sessions($tenant, $branches, $from, $to, $filters),
            'staff' => $this->staff($tenant, $actor, $branches, $from, $to, $filters, $owner),
        };
    }

    private function revenue(Tenant $tenant, Collection $branches, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $payments = DB::table('payments')->join('orders', function ($join): void {
            $join->on('orders.tenant_id', '=', 'payments.tenant_id')->on('orders.id', '=', 'payments.order_id');
        })->join('branches', 'branches.id', '=', 'payments.branch_id')->leftJoin('users', 'users.id', '=', 'payments.posted_by_user_id')
            ->where('payments.tenant_id', $tenant->id)->where('payments.status', 'posted');
        $this->ranges($payments, 'payments.posted_at', 'payments.branch_id', $branches, $from, $to);
        $payments->selectRaw("payments.id, payments.order_id, 'payment' as event_kind, orders.receipt_number, branches.name as branch_name, branches.currency as branch_currency, branches.timezone as branch_timezone, users.name as actor_name, orders.subtotal_minor, orders.discount_minor, orders.tax_minor, payments.amount_minor as paid_minor, 0 as refunded_minor, payments.amount_minor as net_minor, payments.method as payment_method, payments.currency, payments.posted_at as paid_at");

        $refunds = DB::table('refunds')->join('orders', function ($join): void {
            $join->on('orders.tenant_id', '=', 'refunds.tenant_id')->on('orders.id', '=', 'refunds.order_id');
        })->join('payments', function ($join): void {
            $join->on('payments.tenant_id', '=', 'refunds.tenant_id')->on('payments.branch_id', '=', 'refunds.branch_id')->on('payments.order_id', '=', 'refunds.order_id')->on('payments.id', '=', 'refunds.payment_id');
        })->join('branches', 'branches.id', '=', 'refunds.branch_id')->leftJoin('users', 'users.id', '=', 'refunds.executed_by_user_id')
            ->where('refunds.tenant_id', $tenant->id)->where('refunds.status', 'refunded')->whereNotNull('refunds.executed_at');
        $this->ranges($refunds, 'refunds.executed_at', 'refunds.branch_id', $branches, $from, $to);
        $refunds->where('payments.status', 'posted')->selectRaw("refunds.id, refunds.order_id, 'refund' as event_kind, orders.receipt_number, branches.name as branch_name, branches.currency as branch_currency, branches.timezone as branch_timezone, users.name as actor_name, 0 as subtotal_minor, 0 as discount_minor, 0 as tax_minor, 0 as paid_minor, refunds.amount_minor as refunded_minor, -refunds.amount_minor as net_minor, payments.method as payment_method, refunds.currency, refunds.executed_at as paid_at");

        $events = $payments->unionAll($refunds);
        $summary = DB::query()->fromSub(clone $events, 'revenue_events')->selectRaw("SUM(CASE WHEN event_kind = 'payment' THEN 1 ELSE 0 END) as paid_orders, COALESCE(SUM(subtotal_minor),0) as gross_minor, COALESCE(SUM(discount_minor),0) as discounts_minor, COALESCE(SUM(tax_minor),0) as tax_minor, COALESCE(SUM(paid_minor),0) as paid_minor, COALESCE(SUM(refunded_minor),0) as refunded_minor, COALESCE(SUM(paid_minor - refunded_minor),0) as net_minor")->first();
        $summaryByCurrency = DB::query()->fromSub(clone $events, 'revenue_events')->selectRaw("currency, SUM(CASE WHEN event_kind = 'payment' THEN 1 ELSE 0 END) as paid_orders, COALESCE(SUM(subtotal_minor),0) as gross_minor, COALESCE(SUM(discount_minor),0) as discounts_minor, COALESCE(SUM(tax_minor),0) as tax_minor, COALESCE(SUM(paid_minor),0) as paid_minor, COALESCE(SUM(refunded_minor),0) as refunded_minor, COALESCE(SUM(net_minor),0) as net_minor")->groupBy('currency')->get()->mapWithKeys(fn (object $row): array => [(string) $row->currency => $this->moneySummary($row)])->all();
        $query = DB::query()->fromSub($events, 'revenue_events');
        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'oldest' => $query->orderBy('paid_at'),
            'amount_desc' => $query->orderByRaw('(paid_minor + refunded_minor) DESC'),
            'amount_asc' => $query->orderByRaw('(paid_minor + refunded_minor) ASC'),
            default => $query->orderByDesc('paid_at'),
        };

        return [$query->orderByDesc('id'), $this->moneySummary($summary), [
            'summary_by_currency' => $summaryByCurrency,
            'breakdowns' => $this->revenueBreakdowns($tenant, $events),
        ]];
    }

    private function attendance(Tenant $tenant, Collection $branches, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $query = DB::table('play_sessions')->join('branches', 'branches.id', '=', 'play_sessions.branch_id')->join('children', 'children.id', '=', 'play_sessions.child_id')
            ->where('play_sessions.tenant_id', $tenant->id);
        $this->ranges($query, 'play_sessions.started_at', 'play_sessions.branch_id', $branches, $from, $to);
        $summary = ['visits' => (clone $query)->count(), 'completed' => (clone $query)->where('play_sessions.status', 'completed')->count()];
        $query->select(['play_sessions.id', 'branches.name as branch_name', 'branches.timezone as branch_timezone', 'branches.currency as branch_currency', 'play_sessions.started_at', 'play_sessions.ended_at', 'play_sessions.status', 'children.date_of_birth']);
        $this->sortTime($query, 'play_sessions.started_at', 'play_sessions.id', $filters);

        return [$query, $summary, []];
    }

    private function sessions(Tenant $tenant, Collection $branches, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $query = DB::table('play_sessions')->join('branches', 'branches.id', '=', 'play_sessions.branch_id')->join('guardians', 'guardians.id', '=', 'play_sessions.guardian_id')->leftJoin('users', 'users.id', '=', 'play_sessions.ended_by_user_id')
            ->leftJoin('orders', function ($join): void {
                $join->on('orders.tenant_id', '=', 'play_sessions.tenant_id')->on('orders.branch_id', '=', 'play_sessions.branch_id')->on('orders.session_id', '=', 'play_sessions.id');
            })->leftJoin('payments', function ($join): void {
                $join->on('payments.tenant_id', '=', 'orders.tenant_id')->on('payments.branch_id', '=', 'orders.branch_id')->on('payments.order_id', '=', 'orders.id')->where('payments.status', '=', 'posted');
            })->leftJoin('refunds', function ($join): void {
                $join->on('refunds.tenant_id', '=', 'orders.tenant_id')->on('refunds.branch_id', '=', 'orders.branch_id')->on('refunds.order_id', '=', 'orders.id')->where('refunds.status', '=', 'refunded')->whereNotNull('refunds.executed_at');
            })
            ->where('play_sessions.tenant_id', $tenant->id);
        $this->ranges($query, 'play_sessions.started_at', 'play_sessions.branch_id', $branches, $from, $to);
        if (isset($filters['status'])) {
            $query->where('play_sessions.status', $filters['status']);
        }
        $query->when(isset($filters['child_id']), fn (Builder $query) => $query->where('play_sessions.child_id', $filters['child_id']))
            ->when(isset($filters['ticket_id']), fn (Builder $query) => $query->where('play_sessions.ticket_id', $filters['ticket_id']))
            ->when(isset($filters['guardian_phone']), fn (Builder $query) => $query->where('guardians.phone_e164', $filters['guardian_phone']))
            ->when(isset($filters['actor_user_id']), fn (Builder $query) => $query->where(function (Builder $scope) use ($filters): void {
                $scope->where('play_sessions.created_by_user_id', $filters['actor_user_id'])->orWhere('play_sessions.ended_by_user_id', $filters['actor_user_id']);
            }));
        $summary = ['sessions' => (clone $query)->count(), 'adjustment_minor' => (int) DB::table('play_session_adjustments')->where('tenant_id', $tenant->id)->whereIn('session_id', (clone $query)->select('play_sessions.id'))->sum('adjustment_minor')];
        $query->select(['play_sessions.id', 'branches.name as branch_name', 'branches.currency as branch_currency', 'branches.timezone as branch_timezone', 'play_sessions.status', 'play_sessions.started_at', 'play_sessions.ended_at', 'play_sessions.checkout_amount_due_minor', 'play_sessions.checkout_verification_method', 'play_sessions.checkout_override_reason', 'users.name as actor_name', 'orders.id as order_id', 'orders.receipt_number', 'orders.currency as order_currency', 'payments.id as payment_id', 'payments.method as payment_method', 'payments.status as payment_status', 'payments.amount_minor as payment_minor', 'payments.currency as payment_currency', 'payments.posted_at as payment_posted_at', 'refunds.id as refund_id', 'refunds.amount_minor as refund_minor', 'refunds.currency as refund_currency', 'refunds.executed_at as refund_executed_at'])
            ->selectRaw('COALESCE(orders.total_minor, play_sessions.checkout_amount_due_minor) as final_charge_minor')
            ->selectSub(fn ($q) => $q->from('play_session_adjustments')->whereColumn('play_session_adjustments.session_id', 'play_sessions.id')->selectRaw('COALESCE(SUM(adjustment_minor),0)'), 'adjustment_minor')
            ->selectSub(fn ($q) => $q->from('play_session_adjustments')->whereColumn('play_session_adjustments.session_id', 'play_sessions.id')->selectRaw('COALESCE(SUM(extension_units),0)'), 'extension_units');
        $this->sortTime($query, 'play_sessions.started_at', 'play_sessions.id', $filters);

        return [$query, $summary, []];
    }

    private function staff(Tenant $tenant, User $actor, Collection $branches, CarbonImmutable $from, CarbonImmutable $to, array $filters, bool $owner): array
    {
        $query = DB::table('audit_logs')->join('branches', 'branches.id', '=', 'audit_logs.branch_id')->leftJoin('users', 'users.id', '=', 'audit_logs.actor_user_id')
            ->where('audit_logs.tenant_id', $tenant->id)->where('audit_logs.outcome', 'success');
        $this->ranges($query, 'audit_logs.occurred_at', 'audit_logs.branch_id', $branches, $from, $to);
        if (! $owner) {
            $managerBranchIds = DB::table('branch_user')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)
                ->where('is_active', true)->where('role', 'branch_manager')->whereIn('branch_id', $branches->pluck('id'))->pluck('branch_id');
            $query->where(fn (Builder $scope) => $scope->whereIn('audit_logs.branch_id', $managerBranchIds)
                ->orWhere('audit_logs.actor_user_id', $actor->id));
        }
        if (isset($filters['actor_user_id'])) {
            $query->where('audit_logs.actor_user_id', $filters['actor_user_id']);
        }
        $summary = ['actions' => (clone $query)->count(), 'actors' => (clone $query)->distinct()->count('audit_logs.actor_user_id')];
        $query->select(['audit_logs.id', 'branches.name as branch_name', 'branches.timezone as branch_timezone', 'branches.currency as branch_currency', 'users.name as actor_name', 'audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id', 'audit_logs.reason_code', 'audit_logs.occurred_at']);
        $this->sortTime($query, 'audit_logs.occurred_at', 'audit_logs.id', $filters);

        return [$query, $summary, []];
    }

    /** @return array{products: list<array<string, mixed>>, ticket_types: list<array<string, mixed>>, payment_methods: list<array<string, mixed>>} */
    private function revenueBreakdowns(Tenant $tenant, Builder $events): array
    {
        $eventRows = DB::query()->fromSub(clone $events, 'revenue_events')->get([
            'order_id', 'event_kind', 'paid_minor', 'refunded_minor', 'currency', 'payment_method',
        ]);
        $orderIds = $eventRows->pluck('order_id')->filter()->unique()->values();
        $itemsByOrder = $orderIds->isEmpty() ? collect() : DB::table('order_items')
            ->where('tenant_id', $tenant->id)->whereIn('order_id', $orderIds)
            ->orderBy('order_id')->orderBy('line_number')->get()
            ->groupBy('order_id');
        $groups = ['products' => [], 'ticket_types' => [], 'payment_methods' => []];

        foreach ($eventRows as $event) {
            $amount = (int) ($event->paid_minor ?: $event->refunded_minor);
            $currency = (string) $event->currency;
            $method = (string) ($event->payment_method ?: 'unknown');
            $methodKey = $currency.'|'.$method;
            $methodGroup = $groups['payment_methods'][$methodKey] ?? [
                'method' => $method, 'label' => $method, 'currency' => $currency,
                'paid_minor' => 0, 'refunded_minor' => 0, 'net_minor' => 0,
            ];
            if ($event->event_kind === 'payment') {
                $methodGroup['paid_minor'] += $amount;
            } else {
                $methodGroup['refunded_minor'] += $amount;
            }
            $methodGroup['net_minor'] = $methodGroup['paid_minor'] - $methodGroup['refunded_minor'];
            $groups['payment_methods'][$methodKey] = $methodGroup;

            $items = $itemsByOrder->get($event->order_id, collect());
            $lineTotal = (int) $items->sum('line_total_minor');
            if ($lineTotal < 1 || $amount < 1) {
                continue;
            }
            $remaining = $amount;
            $lastIndex = $items->count() - 1;
            foreach ($items->values() as $index => $item) {
                $lineAmount = $index === $lastIndex
                    ? $remaining
                    : intdiv($amount * (int) $item->line_total_minor, $lineTotal);
                $remaining -= $lineAmount;
                $kind = (string) $item->item_kind;
                $id = (int) ($item->product_id ?? $item->ticket_type_id ?? 0);
                $label = trim((string) $item->description_snapshot) ?: ($kind === 'ticket' ? 'Ticket #'.$id : 'Service #'.$id);
                $bucket = $kind === 'ticket' ? 'ticket_types' : 'products';
                $key = $currency.'|'.$kind.'|'.$id.'|'.$label;
                $group = $groups[$bucket][$key] ?? [
                    'id' => $id, 'label' => $label, 'currency' => $currency,
                    'quantity' => 0, 'paid_minor' => 0, 'refunded_minor' => 0, 'net_minor' => 0,
                ];
                if ($event->event_kind === 'payment') {
                    $group['quantity'] += (int) $item->quantity;
                    $group['paid_minor'] += $lineAmount;
                } else {
                    $group['refunded_minor'] += $lineAmount;
                }
                $group['net_minor'] = $group['paid_minor'] - $group['refunded_minor'];
                $groups[$bucket][$key] = $group;
            }
        }

        return array_map(static fn (array $groups): array => array_values($groups), $groups);
    }

    /** @return array<string, int> */
    private function moneySummary(object|array $summary): array
    {
        $summary = (array) $summary;
        unset($summary['currency']);

        return array_map(static fn (mixed $value): int => (int) $value, $summary);
    }

    private function ranges(Builder $query, string $timeColumn, string $branchColumn, Collection $branches, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where(function (Builder $scope) use ($timeColumn, $branchColumn, $branches, $from, $to): void {
            foreach ($branches as $branch) {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $from->toDateString(), $branch->timezone)->utc();
                $end = CarbonImmutable::createFromFormat('!Y-m-d', $to->addDay()->toDateString(), $branch->timezone)->utc();
                $scope->orWhere(fn (Builder $row) => $row->where($branchColumn, $branch->id)->where($timeColumn, '>=', $start)->where($timeColumn, '<', $end));
            }
        });
    }

    private function sortTime(Builder $query, string $column, string $idColumn, array $filters): void
    {
        ($filters['sort'] ?? 'newest') === 'oldest' ? $query->orderBy($column) : $query->orderByDesc($column);
        $query->orderByDesc($idColumn);
    }

    private function csvHeaders(string $type): array
    {
        return match ($type) {
            'revenue' => ['id', 'receipt_number', 'branch', 'actor', 'gross_minor', 'discount_minor', 'tax_minor', 'paid_minor', 'refunded_minor', 'net_minor', 'payment_method', 'currency', 'branch_currency', 'branch_timezone', 'occurred_at_utc'],
            'attendance' => ['id', 'branch', 'started_at_utc', 'ended_at_utc', 'status', 'age_band', 'branch_currency', 'branch_timezone'],
            'sessions' => ['id', 'branch', 'status', 'started_at_utc', 'ended_at_utc', 'duration_seconds', 'extension_units', 'adjustment_minor', 'final_charge_minor', 'payment_method', 'payment_status', 'payment_minor', 'refund_minor', 'receipt_number', 'verification', 'override_reason', 'branch_currency', 'branch_timezone'],
            'staff' => ['id', 'branch', 'actor', 'action', 'subject_type', 'subject_id', 'reason', 'occurred_at_utc', 'branch_currency', 'branch_timezone'],
        };
    }

    private function csvRow(string $type, object $row): array
    {
        return match ($type) {
            'revenue' => [$row->id, $row->receipt_number, $row->branch_name, $row->actor_name, $row->subtotal_minor, $row->discount_minor, $row->tax_minor, $row->paid_minor, $row->refunded_minor, $row->net_minor, $row->payment_method, $row->currency, $row->branch_currency, $row->branch_timezone, $row->paid_at],
            'attendance' => [$row->id, $row->branch_name, $row->started_at, $row->ended_at, $row->status, $this->ageBand($row->date_of_birth, $row->started_at), $row->branch_currency, $row->branch_timezone],
            'sessions' => [$row->id, $row->branch_name, $row->status, $row->started_at, $row->ended_at, $row->ended_at ? CarbonImmutable::parse($row->started_at)->diffInSeconds(CarbonImmutable::parse($row->ended_at)) : null, $row->extension_units, $row->adjustment_minor, $row->final_charge_minor, $row->payment_method, $row->payment_status, $row->payment_minor, $row->refund_minor, $row->receipt_number, $row->checkout_verification_method, $row->checkout_override_reason, $row->branch_currency, $row->branch_timezone],
            'staff' => [$row->id, $row->branch_name, $row->actor_name, $row->action, $row->subject_type, $row->subject_id, $row->reason_code, $row->occurred_at, $row->branch_currency, $row->branch_timezone],
        };
    }

    private function ageBand(?string $birthDate, string $at): string
    {
        if (! $birthDate) {
            return 'unknown';
        }
        $years = CarbonImmutable::parse($birthDate)->diffInYears(CarbonImmutable::parse($at));

        return $years < 4 ? '0-3' : ($years < 7 ? '4-6' : ($years < 13 ? '7-12' : '13+'));
    }
}
