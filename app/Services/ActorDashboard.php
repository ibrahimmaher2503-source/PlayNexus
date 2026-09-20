<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Guardian;
use App\Models\PlaySession;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\PosPolicy;
use App\Support\SubscriptionAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Read-only composition; client role/tenant/branch query parameters never choose scope. */
final class ActorDashboard
{
    public function show(Request $request)
    {
        $user = $request->user();
        $tenant = app(TenantContext::class)->current($user);
        $owner = Gate::forUser($user)->allows('view', $tenant);
        $branchQuery = $user->accessibleBranches();
        if (! $owner) {
            $branchQuery->whereExists(function (QueryBuilder $query) use ($user, $tenant): void {
                $query->selectRaw('1')->from('branch_user')
                    ->whereColumn('branch_user.branch_id', 'branches.id')
                    ->where('branch_user.tenant_id', $tenant->id)
                    ->where('branch_user.user_id', $user->id)
                    ->where('branch_user.is_active', true)
                    ->where(function (QueryBuilder $query): void {
                        $query->whereIn('branch_user.role', ['branch_manager', 'reception_staff', 'reception', 'cashier'])
                            ->orWhereExists(function (QueryBuilder $query): void {
                                $query->selectRaw('1')->from('custom_roles')
                                    ->join('custom_role_permissions', 'custom_role_permissions.custom_role_id', '=', 'custom_roles.id')
                                    ->whereColumn('custom_roles.tenant_id', 'branch_user.tenant_id')
                                    ->whereColumn('custom_roles.code', 'branch_user.role')
                                    ->where('custom_role_permissions.permission', 'branches.view');
                            });
                    });
            });
        }
        $branches = $branchQuery->orderBy('name')->get();
        $selectedBranch = $branches->firstWhere('id', $request->session()->get('branch_id'));
        if (! $selectedBranch && $branches->count() === 1) {
            $selectedBranch = $branches->first();
            Gate::forUser($user)->authorize('view', $selectedBranch);
            $request->session()->put('branch_id', $selectedBranch->id);
        }
        $role = $owner ? 'owner' : ($selectedBranch ? DB::table('branch_user')->where('tenant_id', $tenant->id)->where('user_id', $user->id)->where('branch_id', $selectedBranch->id)->where('is_active', true)->value('role') : 'staff');
        $role = in_array($role, ['owner', 'branch_manager', 'reception', 'reception_staff', 'cashier'], true) ? $role : 'staff';
        $role = $role === 'reception_staff' ? 'reception' : $role;
        $finance = $selectedBranch && ($owner || app(PosPolicy::class)->payOrder($user, $selectedBranch));
        $operational = $selectedBranch && Gate::forUser($user)->allows('viewBranch', [PlaySession::class, $selectedBranch]);
        $summary = $selectedBranch && $role !== 'staff' ? $this->summaries($tenant, collect([$selectedBranch]), (bool) $finance)->get($selectedBranch->id) : null;
        $overview = $owner ? Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_active')->orderBy('name')->paginate(12, ['*'], 'branches_page') : null;
        $branchSummaries = $overview ? $this->summaries($tenant, collect($overview->items()), true) : collect();
        $totals = $owner ? [
            'branches' => Branch::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'staff' => User::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count(),
            'active' => DB::table('play_sessions')->where('tenant_id', $tenant->id)->where('status', 'active')->count(),
            'pending' => DB::table('play_sessions')->where('tenant_id', $tenant->id)->where('status', 'pending_payment')->count(),
        ] : null;
        $actions = [];
        $add = function (string $key, string $route, array $params = [], bool $allowed = true) use (&$actions): void {
            if ($allowed) {
                $actions[] = ['key' => $key, 'url' => route($route, $params)];
            }
        };
        if ($owner) {
            $add('branches', 'branches.manage');
            $add('staff', 'staff.index');
            $add('reports', 'reports.index', [], $overview->total() > 0);
            $add('settings', 'tenant.settings.edit');
            $add('families', 'families.index', [], $selectedBranch && Gate::forUser($user)->allows('viewAny', Guardian::class));
            $add('tickets', 'tickets.index', [], $selectedBranch && Gate::forUser($user)->allows('viewAny', Ticket::class));
        } elseif ($selectedBranch && $role !== 'staff') {
            if ($role === 'cashier') {
                $add('pos', 'pos.index', [], (bool) $finance);
                $add('pending', 'sessions.index', ['status' => 'pending_payment'], (bool) $operational);
                $add('transactions', 'transactions.index', [], (bool) $finance);
            } else {
                $add($role === 'reception' ? 'families' : 'sessions', $role === 'reception' ? 'families.index' : 'sessions.index', [], $role === 'reception' ? Gate::forUser($user)->allows('viewAny', Guardian::class) : (bool) $operational);
                $add('tickets', 'tickets.index', [], Gate::forUser($user)->allows('viewAny', Ticket::class));
                $add($role === 'reception' ? 'sessions' : 'pending', 'sessions.index', $role === 'reception' ? [] : ['status' => 'pending_payment'], (bool) $operational);
                if ($role === 'branch_manager') {
                    $add('pos', 'pos.index', [], (bool) $finance);
                    $add('reports', 'reports.index', ['branch_id' => $selectedBranch->id]);
                    $add('branch_settings', 'branches.settings', ['branch' => $selectedBranch], Gate::forUser($user)->allows('update', $selectedBranch));
                }
            }
        }
        $accountPhase = $owner ? SubscriptionAccess::forTenant($tenant)->phase() : null;

        return view('dashboard', compact('tenant', 'branches', 'selectedBranch', 'role', 'owner', 'finance', 'operational', 'summary', 'overview', 'branchSummaries', 'totals', 'actions', 'accountPhase'));
    }

    /** Fixed aggregate-query count; branch-detail pages are bounded to 12 rows. */
    private function summaries(Tenant $tenant, Collection $branches, bool $finance): Collection
    {
        if ($branches->isEmpty()) {
            return collect();
        }
        $now = CarbonImmutable::now('UTC');
        $ranges = $branches->mapWithKeys(function (Branch $branch) use ($now): array {
            $start = $now->setTimezone($branch->timezone)->startOfDay();

            return [$branch->id => [$start->utc(), $start->addDay()->utc(), $start->toDateString()]];
        });
        $query = fn (string $table) => DB::table($table)->where('tenant_id', $tenant->id)->whereIn('branch_id', $branches->pluck('id'));
        $today = function ($query, string $column) use ($ranges): void {
            $query->where(function ($query) use ($ranges, $column): void {
                foreach ($ranges as $id => [$start, $end]) {
                    $query->orWhere(fn ($query) => $query->where('branch_id', $id)->where($column, '>=', $start)->where($column, '<', $end));
                }
            });
        };
        $sessions = $query('play_sessions')->select('branch_id')->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN status = 'pending_payment' THEN 1 ELSE 0 END) AS pending")
            ->selectRaw("SUM(CASE WHEN status = 'active' AND expected_end_at < ? THEN 1 ELSE 0 END) AS overdue", [$now])
            ->selectRaw("SUM(CASE WHEN status = 'active' AND expected_end_at >= ? AND expected_end_at <= ? THEN 1 ELSE 0 END) AS due", [$now, $now->addMinutes(15)])
            ->whereIn('status', ['active', 'pending_payment'])->groupBy('branch_id')->get()->keyBy('branch_id');
        $attendanceQuery = $query('play_sessions');
        $today($attendanceQuery, 'started_at');
        $attendance = $attendanceQuery->select('branch_id')->selectRaw('COUNT(*) AS total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $ticketsQuery = $query('tickets');
        $today($ticketsQuery, 'issued_at');
        $tickets = $ticketsQuery->select('branch_id')->selectRaw('COUNT(*) AS total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $payments = $refunds = $receipts = collect();
        if ($finance) {
            $paymentsQuery = $query('payments')->where('status', 'posted')->where('method', 'cash');
            $today($paymentsQuery, 'posted_at');
            $payments = $paymentsQuery->select('branch_id', 'currency')->selectRaw('SUM(amount_minor) AS amount')->groupBy('branch_id', 'currency')->get()->groupBy('branch_id');
            $refundsQuery = $query('refunds')->where('status', 'refunded');
            $today($refundsQuery, 'executed_at');
            $refunds = $refundsQuery->select('branch_id', 'currency')->selectRaw('SUM(amount_minor) AS amount')->groupBy('branch_id', 'currency')->get()->groupBy('branch_id');
            $receiptsQuery = $query('orders')->whereNotNull('receipt_number');
            $today($receiptsQuery, 'receipt_issued_at');
            $receipts = $receiptsQuery->select('branch_id')->selectRaw('COUNT(*) AS total')->groupBy('branch_id')->pluck('total', 'branch_id');
        }

        return $branches->mapWithKeys(function (Branch $branch) use ($sessions, $attendance, $tickets, $payments, $refunds, $receipts, $ranges, $now, $finance): array {
            $money = [];
            foreach ($payments->get($branch->id, collect()) as $payment) {
                $money[$payment->currency] = (int) $payment->amount;
            }
            foreach ($refunds->get($branch->id, collect()) as $refund) {
                $money[$refund->currency] = ($money[$refund->currency] ?? 0) - (int) $refund->amount;
            }

            return [$branch->id => [
                'active' => (int) ($sessions->get($branch->id)?->active ?? 0),
                'pending' => (int) ($sessions->get($branch->id)?->pending ?? 0),
                'overdue' => (int) ($sessions->get($branch->id)?->overdue ?? 0),
                'due' => (int) ($sessions->get($branch->id)?->due ?? 0),
                'attendance' => (int) $attendance->get($branch->id, 0),
                'tickets' => (int) $tickets->get($branch->id, 0),
                'receipts' => $finance ? (int) $receipts->get($branch->id, 0) : null,
                'money' => $finance ? $money : null,
                'date' => $ranges[$branch->id][2],
                'updated' => $now->setTimezone($branch->timezone)->format('H:i'),
            ]];
        });
    }
}
