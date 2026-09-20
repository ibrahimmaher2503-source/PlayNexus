@extends('layouts.app')

@section('title', __('reports.page_title').' · PlayNexus')

@section('content')
@php
    $reportCurrency = $selected->pluck('currency')->filter()->unique()->count() === 1 ? $selected->first()->currency : __('reports.mixed_context');
    $reportTimezone = $selected->pluck('timezone')->filter()->unique()->count() === 1 ? $selected->first()->timezone : __('reports.mixed_context');
@endphp
<main class="mx-auto min-h-screen max-w-[1600px] px-4 py-6 sm:px-6">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
        <div>
            <h1 class="text-2xl font-bold">{{ __('reports.page_title') }}</h1>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('reports.description') }}</p>
            <p class="mt-2 text-xs text-[var(--pn-ink-muted)]" data-report-context>{{ __('reports.context', ['currency' => $reportCurrency, 'timezone' => $reportTimezone]) }}</p>
        </div>
        @if ($owner || $manager)
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold text-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('reports.export', ['type' => $type] + request()->query()) }}">{{ __('reports.export_csv') }}</a>
        @endif
    </header>

    <nav class="mt-5 flex flex-wrap gap-2" aria-label="{{ __('reports.report_types') }}">
        @foreach ($availableTypes as $reportType)
            <a class="inline-flex min-h-11 items-center rounded-[10px] px-4 font-semibold {{ $type === $reportType ? 'bg-[var(--pn-primary)] text-white' : 'border border-[var(--pn-border)] bg-[var(--pn-surface)]' }}" href="{{ route('reports.index', ['type' => $reportType]) }}">{{ __('reports.types.'.$reportType) }}</a>
        @endforeach
    </nav>

    @if ($errors->any())
        <div class="mt-5 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4" role="alert" tabindex="-1">
            <p class="font-semibold">{{ __('reports.fix_filters') }}</p>
            <ul class="mt-2 list-inside list-disc text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="mt-5 grid gap-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5" method="GET" action="{{ route('reports.index', ['type' => $type]) }}">
        <div>
            <label class="block text-sm font-semibold" for="branch_id">{{ __('reports.branch') }}</label>
            <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="branch_id" name="branch_id">
                <option value="">{{ __('reports.all_permitted_branches') }}</option>
                @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="block text-sm font-semibold" for="from">{{ __('reports.from') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="from" name="from" type="date" value="{{ request('from', $from->toDateString()) }}"></div>
        <div><label class="block text-sm font-semibold" for="to">{{ __('reports.to') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="to" name="to" type="date" value="{{ request('to', $to->toDateString()) }}"></div>
        @if ($type === 'sessions')
            <div><label class="block text-sm font-semibold" for="status">{{ __('reports.status') }}</label><select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="status" name="status"><option value="">{{ __('reports.all_statuses') }}</option>@foreach (['active','pending_payment','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('reports.statuses.'.$status) }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-semibold" for="child_id">{{ __('reports.child_id') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="child_id" name="child_id" type="number" min="1" value="{{ request('child_id') }}"></div>
            <div><label class="block text-sm font-semibold" for="ticket_id">{{ __('reports.ticket_id') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="ticket_id" name="ticket_id" type="number" min="1" value="{{ request('ticket_id') }}"></div>
            <div><label class="block text-sm font-semibold" for="guardian_phone">{{ __('reports.guardian_phone') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="guardian_phone" name="guardian_phone" type="tel" inputmode="tel" value="{{ request('guardian_phone') }}"></div>
            <div><label class="block text-sm font-semibold" for="actor_user_id">{{ __('reports.staff_id') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="actor_user_id" name="actor_user_id" type="number" min="1" value="{{ request('actor_user_id') }}"></div>
        @else
            <div><label class="block text-sm font-semibold" for="sort">{{ __('reports.sort') }}</label><select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="sort" name="sort"><option value="newest">{{ __('reports.newest') }}</option><option value="oldest" @selected(request('sort') === 'oldest')>{{ __('reports.oldest') }}</option>@if ($type === 'revenue')<option value="amount_desc" @selected(request('sort') === 'amount_desc')>{{ __('reports.amount_desc') }}</option><option value="amount_asc" @selected(request('sort') === 'amount_asc')>{{ __('reports.amount_asc') }}</option>@endif</select></div>
        @endif
        <button class="min-h-11 self-end rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-white" type="submit">{{ __('reports.apply') }}</button>
    </form>

    <section class="mt-5" aria-labelledby="summary-heading">
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-bold" id="summary-heading">{{ __('reports.summary') }}</h2><p class="text-sm text-[var(--pn-ink-muted)]">{{ __('reports.generated', ['time' => $generatedAt]) }}</p></div>
        @if ($type === 'revenue')
            @foreach (($summaryByCurrency ?: [$reportCurrency => $summary]) as $summaryCurrency => $currencySummary)
                <p class="mt-4 text-sm font-semibold text-[var(--pn-ink-muted)]" data-report-currency>{{ __('reports.currency_total', ['currency' => $summaryCurrency]) }}</p>
                <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    @foreach ($currencySummary as $key => $value)
                        <div class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-4"><p class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('reports.metrics.'.$key) }}</p><p class="mt-1 text-xl font-bold tabular-nums" dir="ltr">{{ str_contains($key, 'minor') ? number_format(((int) $value) / 100, 2).' '.e($summaryCurrency) : number_format((int) $value) }}</p></div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                @foreach ($summary as $key => $value)
                    <div class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-4"><p class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('reports.metrics.'.$key) }}</p><p class="mt-1 text-xl font-bold tabular-nums" dir="ltr">{{ number_format((int) $value) }}</p></div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($type === 'revenue')
        <section class="mt-6" aria-labelledby="breakdowns-heading">
            <h2 class="text-lg font-bold" id="breakdowns-heading">{{ __('reports.breakdowns.title') }}</h2>
            <div class="mt-3 grid gap-4 lg:grid-cols-3">
                @foreach (['products', 'ticket_types', 'payment_methods'] as $breakdownKey)
                    <article class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-4">
                        <h3 class="font-semibold">{{ __('reports.breakdowns.'.$breakdownKey) }}</h3>
                        @if (empty($breakdowns[$breakdownKey]))
                            <p class="mt-3 text-sm text-[var(--pn-ink-muted)]">{{ __('reports.empty') }}</p>
                        @else
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full min-w-[360px] text-sm"><caption class="sr-only">{{ __('reports.breakdowns.'.$breakdownKey) }}</caption><thead><tr><th class="px-2 py-2 text-start" scope="col">{{ __('reports.breakdowns.item') }}</th><th class="px-2 py-2 text-end" scope="col">{{ __('reports.breakdowns.paid') }}</th><th class="px-2 py-2 text-end" scope="col">{{ __('reports.breakdowns.refunded') }}</th><th class="px-2 py-2 text-end" scope="col">{{ __('reports.breakdowns.net') }}</th></tr></thead>
                                    <tbody class="divide-y divide-[var(--pn-border)]">
                                    @foreach ($breakdowns[$breakdownKey] as $breakdown)
                                        <tr><th class="px-2 py-2 text-start font-medium" scope="row">{{ $breakdown['label'] ?? __('reports.unknown') }} @if (!empty($breakdown['quantity']))<span class="text-xs text-[var(--pn-ink-muted)]">×{{ $breakdown['quantity'] }}</span>@endif</th><td class="px-2 py-2 text-end tabular-nums" dir="ltr">{{ number_format(((int) $breakdown['paid_minor']) / 100, 2).' '.e($breakdown['currency']) }}</td><td class="px-2 py-2 text-end tabular-nums" dir="ltr">{{ number_format(((int) $breakdown['refunded_minor']) / 100, 2).' '.e($breakdown['currency']) }}</td><td class="px-2 py-2 text-end font-semibold tabular-nums" dir="ltr">{{ number_format(((int) $breakdown['net_minor']) / 100, 2).' '.e($breakdown['currency']) }}</td></tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-6" aria-labelledby="results-heading">
        <h2 class="text-lg font-bold" id="results-heading">{{ __('reports.results') }}</h2>
        @if ($rows->isEmpty())
            <div class="mt-3 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-8 text-center text-[var(--pn-ink-muted)]">{{ __('reports.empty') }}</div>
        @else
            <div class="mt-3 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]" data-pn-responsive-table>
                <table class="w-full min-w-[1000px] text-sm"><caption class="sr-only">{{ __('reports.results') }}</caption>
                    <thead class="bg-[var(--pn-surface-subtle)]"><tr>
                        @foreach (__('reports.columns.'.$type) as $column)<th class="px-4 py-3 text-start" scope="col">{{ $column }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--pn-border)]">
                    @foreach ($rows as $row)
                        <tr>
                            @if ($type === 'revenue')
                                @php($eventTime = \Illuminate\Support\Carbon::parse((string) $row->paid_at, 'UTC')->setTimezone($row->branch_timezone ?: $tenant->timezone))
                                <td class="px-4 py-3" data-label="{{ __('reports.columns.revenue.0') }}"><bdi dir="ltr">{{ $row->receipt_number }}</bdi></td><td class="px-4 py-3" data-label="{{ __('reports.columns.revenue.1') }}">{{ $row->branch_name }}</td><td class="px-4 py-3" data-label="{{ __('reports.columns.revenue.2') }}">{{ $row->actor_name ?: '—' }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.revenue.3') }}" dir="ltr">{{ number_format(((int) $row->paid_minor) / 100, 2).' '.e($row->currency) }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.revenue.4') }}" dir="ltr">{{ number_format(((int) $row->refunded_minor) / 100, 2).' '.e($row->currency) }}</td><td class="px-4 py-3 font-semibold tabular-nums" data-label="{{ __('reports.columns.revenue.5') }}" dir="ltr">{{ number_format(((int) $row->net_minor) / 100, 2).' '.e($row->currency) }}</td><td class="px-4 py-3" data-label="{{ __('reports.columns.revenue.6') }}">{{ __('reports.payment_methods.'.($row->payment_method ?: 'unknown')) }}</td><td class="px-4 py-3" data-label="{{ __('reports.columns.revenue.7') }}"><time datetime="{{ $eventTime->toIso8601String() }}" dir="ltr">{{ $eventTime->format('Y-m-d H:i') }} {{ $row->branch_timezone }}</time></td>
                            @elseif ($type === 'attendance')
                                @php($started = \Illuminate\Support\Carbon::parse((string) $row->started_at, 'UTC')->setTimezone($row->branch_timezone ?: $tenant->timezone))
                                @php($age = $row->date_of_birth ? \Illuminate\Support\Carbon::parse($row->date_of_birth)->diffInYears(\Illuminate\Support\Carbon::parse($row->started_at)) : null)
                                <td class="px-4 py-3"><bdi dir="ltr">#{{ $row->id }}</bdi></td><td class="px-4 py-3">{{ $row->branch_name }}</td><td class="px-4 py-3"><time datetime="{{ $started->toIso8601String() }}" dir="ltr">{{ $started->format('Y-m-d H:i') }} {{ $row->branch_timezone }}</time></td><td class="px-4 py-3">{{ __('reports.statuses.'.$row->status) }}</td><td class="px-4 py-3">{{ $age === null ? __('reports.unknown_age') : ($age < 4 ? '0–3' : ($age < 7 ? '4–6' : ($age < 13 ? '7–12' : '13+'))) }}</td>
                            @elseif ($type === 'sessions')
                                @php($started = \Illuminate\Support\Carbon::parse((string) $row->started_at, 'UTC')->setTimezone($row->branch_timezone ?: $tenant->timezone))
                                @php($ended = $row->ended_at ? \Illuminate\Support\Carbon::parse((string) $row->ended_at, 'UTC')->setTimezone($row->branch_timezone ?: $tenant->timezone) : null)
                                @php($duration = $row->ended_at ? \Illuminate\Support\Carbon::parse((string) $row->started_at, 'UTC')->diffInSeconds(\Illuminate\Support\Carbon::parse((string) $row->ended_at, 'UTC')) : null)
                                @php($sessionCurrency = $row->order_currency ?: $row->branch_currency ?: $tenant->currency)
                                <td class="px-4 py-3" data-label="{{ __('reports.columns.sessions.0') }}"><bdi dir="ltr">#{{ $row->id }}</bdi></td><td class="px-4 py-3" data-label="{{ __('reports.columns.sessions.1') }}">{{ $row->branch_name }}</td><td class="px-4 py-3" data-label="{{ __('reports.columns.sessions.2') }}">{{ __('reports.statuses.'.$row->status) }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.sessions.3') }}" dir="ltr">{{ $duration === null ? '—' : gmdate('H:i:s', $duration) }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.sessions.4') }}" dir="ltr">{{ number_format(((int) $row->extension_units) * 30) }} {{ __('reports.minutes') }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.sessions.5') }}" dir="ltr">{{ number_format(((int) $row->adjustment_minor) / 100, 2).' '.e($sessionCurrency) }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.sessions.6') }}" dir="ltr">{{ $row->final_charge_minor === null ? '—' : number_format(((int) $row->final_charge_minor) / 100, 2).' '.e($sessionCurrency) }}</td><td class="px-4 py-3" data-label="{{ __('reports.columns.sessions.7') }}">{{ $row->payment_method ? __('reports.payment_methods.'.$row->payment_method) : '—' }}{{ $row->payment_status ? ' · '.__('reports.payment_statuses.'.$row->payment_status) : '' }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.sessions.8') }}" dir="ltr">{{ $row->payment_minor === null ? '—' : number_format(((int) $row->payment_minor) / 100, 2).' '.e($row->payment_currency ?: $sessionCurrency) }}</td><td class="px-4 py-3 tabular-nums" data-label="{{ __('reports.columns.sessions.9') }}" dir="ltr">{{ $row->refund_minor === null ? '—' : number_format(((int) $row->refund_minor) / 100, 2).' '.e($row->refund_currency ?: $sessionCurrency) }}</td><td class="px-4 py-3" data-label="{{ __('reports.columns.sessions.10') }}"><bdi dir="ltr">{{ $row->receipt_number ?: '—' }}</bdi></td><td class="px-4 py-3" data-label="{{ __('reports.columns.sessions.11') }}">{{ $row->checkout_verification_method ?: '—' }}</td><td class="max-w-[18rem] px-4 py-3" data-label="{{ __('reports.columns.sessions.12') }}">{{ $row->checkout_override_reason ?: '—' }}</td><td class="px-4 py-3" data-label="{{ __('reports.columns.sessions.13') }}"><time datetime="{{ $started->toIso8601String() }}" dir="ltr">{{ $started->format('Y-m-d H:i') }} {{ $row->branch_timezone }}</time>@if($ended)<br><time datetime="{{ $ended->toIso8601String() }}" dir="ltr">{{ $ended->format('Y-m-d H:i') }} {{ $row->branch_timezone }}</time>@endif</td>
                            @else
                                @php($occurred = \Illuminate\Support\Carbon::parse((string) $row->occurred_at, 'UTC')->setTimezone($row->branch_timezone ?: $tenant->timezone))
                                <td class="px-4 py-3"><time datetime="{{ $occurred->toIso8601String() }}" dir="ltr">{{ $occurred->format('Y-m-d H:i') }} {{ $row->branch_timezone }}</time></td><td class="px-4 py-3">{{ $row->branch_name }}</td><td class="px-4 py-3">{{ $row->actor_name ?: '—' }}</td><td class="px-4 py-3"><bdi dir="ltr">{{ $row->action }}</bdi></td><td class="px-4 py-3"><bdi dir="ltr">{{ $row->subject_type }} #{{ $row->subject_id }}</bdi></td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    </section>
</main>
@endsection
