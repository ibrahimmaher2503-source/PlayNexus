@extends('layouts.app')

@section('title', __('actor_dashboard.titles.'.$role).' · PlayNexus')

@section('content')
<main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6 lg:py-8" data-pn-dashboard data-pn-actor="{{ $role }}">
    <header class="flex flex-wrap items-end justify-between gap-5 border-b border-[var(--pn-border)] pb-5">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('dashboard.roles.'.$role) }}</p>
            <h1 class="mt-1 text-2xl font-bold sm:text-[1.75rem]">{{ __('actor_dashboard.titles.'.$role) }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.descriptions.'.$role) }}</p>
        </div>
        @if ($selectedBranch)
            <div class="flex min-h-14 items-center gap-3 rounded-[12px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-4" aria-label="{{ __('actor_dashboard.current_branch') }}">
                <span class="size-2.5 rounded-full bg-[var(--pn-success)]" aria-hidden="true"></span>
                <span><small class="block text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.current_branch') }}</small><strong class="block max-w-56 truncate"><bdi dir="auto">{{ $selectedBranch->name }}</bdi></strong></span>
            </div>
        @endif
    </header>

    @if ($owner && $accountPhase === \App\Support\SubscriptionAccess::RESTRICTED)
        <a class="mt-5 block rounded-[12px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] px-4 py-3 font-semibold text-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('tenant.subscription.show') }}" role="alert">{{ __('actor_dashboard.subscription_restricted') }}</a>
    @elseif ($owner && $accountPhase === \App\Support\SubscriptionAccess::GRACE)
        <a class="mt-5 block rounded-[12px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] px-4 py-3 font-semibold text-[var(--pn-warning)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('tenant.subscription.show') }}" role="status">{{ __('actor_dashboard.subscription_grace') }}</a>
    @endif

    @if ($owner)
        <a class="mt-5 flex min-h-14 items-center justify-between gap-4 rounded-[12px] border border-[var(--pn-border)] bg-[var(--pn-primary-soft)] px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('tenant.setup') }}">
            <span><strong class="block text-sm text-[var(--pn-primary)]">{{ __('actor_dashboard.setup') }}</strong><span class="mt-1 block text-sm text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.setup_help') }}</span></span>
            <span class="text-xl text-[var(--pn-primary)] rtl:rotate-180" aria-hidden="true">›</span>
        </a>
        <section class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-border)] xl:grid-cols-4" aria-label="{{ __('actor_dashboard.titles.owner') }}">
            @foreach ([['active_branches','branches'],['active_staff','staff'],['active_sessions','active'],['pending_payments','pending']] as [$label,$key])
                <div class="bg-[var(--pn-surface)] p-4 sm:p-5"><p class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.'.$label) }}</p><p class="mt-2 text-2xl font-bold tabular-nums"><bdi dir="ltr">{{ $totals[$key] }}</bdi></p></div>
            @endforeach
        </section>
    @elseif (! $selectedBranch)
        <section class="mt-7" aria-labelledby="branch-choice-heading">
            <h2 class="text-lg font-bold" id="branch-choice-heading">{{ __('actor_dashboard.choose_branch') }}</h2>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.choose_branch_help') }}</p>
            @if ($branches->isEmpty())
                <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status"><strong>{{ __('actor_dashboard.no_branch') }}</strong><p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.no_branch_staff') }}</p></div>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($branches as $branch)
                        <form method="POST" action="{{ route('branch-context.store', $branch) }}">@csrf<button class="flex min-h-16 w-full items-center justify-between rounded-[12px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-4 text-start font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit"><bdi dir="auto">{{ $branch->name }}</bdi><span aria-hidden="true" class="rtl:rotate-180">›</span></button></form>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if ($selectedBranch && $summary && ! $owner)
        @if ($summary['overdue'] || $summary['pending'])
            <section class="mt-5 grid gap-3 sm:grid-cols-2" aria-labelledby="attention-heading">
                <h2 class="sr-only" id="attention-heading">{{ __('actor_dashboard.attention') }}</h2>
                @if ($summary['overdue'])<a class="rounded-[12px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] px-4 py-3 font-semibold text-[var(--pn-warning)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('sessions.index', ['status'=>'active']) }}">{{ __('actor_dashboard.overdue', ['count'=>$summary['overdue']]) }}</a>@endif
                @if ($summary['pending'] && in_array($role, ['branch_manager','cashier'], true))<a class="rounded-[12px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] px-4 py-3 font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('sessions.index', ['status'=>'pending_payment']) }}">{{ __('actor_dashboard.pending_payments') }}: <bdi dir="ltr">{{ $summary['pending'] }}</bdi></a>@endif
            </section>
        @endif
    @endif

    @if ($actions)
        <section class="mt-6" aria-labelledby="quick-actions-heading">
            <h2 class="text-lg font-bold" id="quick-actions-heading">{{ __('actor_dashboard.quick_actions') }}</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($actions as $index=>$action)
                    <a class="flex min-h-20 items-center justify-between gap-4 rounded-[14px] {{ $index === 0 ? 'bg-[var(--pn-primary)] text-[var(--pn-surface)]' : 'border border-[var(--pn-border)] bg-[var(--pn-surface)] text-[var(--pn-ink)]' }} px-5 py-4 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" href="{{ $action['url'] }}" @if($index===0)data-pn-primary-action @endif><span><strong class="block">{{ __('actor_dashboard.actions.'.$action['key'].'.label') }}</strong><span class="mt-1 block text-sm {{ $index===0 ? 'opacity-85' : 'text-[var(--pn-ink-muted)]' }}">{{ __('actor_dashboard.actions.'.$action['key'].'.help') }}</span></span><span class="text-xl rtl:rotate-180" aria-hidden="true">›</span></a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($selectedBranch && $summary && ! $owner)
        <section class="mt-6 overflow-hidden rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]" aria-labelledby="today-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-[var(--pn-border)] px-5 py-4"><div><h2 class="text-lg font-bold" id="today-heading">{{ __('actor_dashboard.today') }} <bdi class="block sm:inline" dir="auto">{{ $selectedBranch->name }}</bdi></h2><p class="mt-1 text-xs text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ __('actor_dashboard.local_date', ['date'=>$summary['date']]) }}</bdi></p></div><p class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.updated', ['time'=>$summary['updated']]) }}</p></div>
            <dl class="grid grid-cols-2 gap-px bg-[var(--pn-border)] {{ $finance ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
                <div class="bg-[var(--pn-surface)] p-4 sm:p-5"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.active_sessions') }}</dt><dd class="mt-2 text-2xl font-bold tabular-nums"><bdi dir="ltr">{{ $summary['active'] }}</bdi></dd><p class="mt-2 text-xs text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.capacity', ['used'=>$summary['active'],'total'=>$selectedBranch->capacity]) }}</p></div>
                <div class="bg-[var(--pn-surface)] p-4 sm:p-5"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.attendance') }}</dt><dd class="mt-2 text-2xl font-bold tabular-nums"><bdi dir="ltr">{{ $summary['attendance'] }}</bdi></dd><p class="mt-2 text-xs text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.due_soon', ['count'=>$summary['due']]) }}</p></div>
                <div class="bg-[var(--pn-surface)] p-4 sm:p-5 {{ $finance ? '' : 'col-span-2 xl:col-span-1' }}"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.tickets') }}</dt><dd class="mt-2 text-2xl font-bold tabular-nums"><bdi dir="ltr">{{ $summary['tickets'] }}</bdi></dd></div>
                @if ($finance)<div class="bg-[var(--pn-surface)] p-4 sm:p-5"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.cash_sales') }}</dt>@forelse ($summary['money'] as $currency=>$amount)<dd class="mt-2 text-xl font-bold tabular-nums"><bdi dir="ltr">{{ $currency }} {{ number_format($amount/100,2) }}</bdi></dd>@empty<p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.no_sales') }}</p>@endforelse<p class="mt-2 text-xs text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.receipts') }}: <bdi dir="ltr">{{ $summary['receipts'] }}</bdi></p></div>@endif
            </dl>
        </section>
    @endif

    @if ($owner)
        <section class="mt-10" aria-labelledby="branch-overview-heading">
            <h2 class="text-lg font-bold" id="branch-overview-heading">{{ __('actor_dashboard.branch_overview') }}</h2><p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.branch_overview_help') }}</p>
            @if ($overview->isEmpty())
                <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status"><strong>{{ __('actor_dashboard.no_branch') }}</strong><p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.no_branch_owner') }}</p></div>
            @else
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach ($overview as $branch) @php($branchSummary=$branchSummaries->get($branch->id))
                        <article class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5">
                            <header class="flex items-start justify-between gap-3"><div><h3 class="font-bold"><bdi dir="auto">{{ $branch->name }}</bdi></h3><p class="mt-1 text-xs text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $branch->timezone }} · {{ $branchSummary['date'] }}</bdi></p></div><span class="rounded-full bg-[var(--pn-surface-subtle)] px-3 py-1 text-xs font-semibold {{ $branch->is_active ? 'text-[var(--pn-success)]' : 'text-[var(--pn-ink-muted)]' }}">{{ __('actor_dashboard.'.($branch->is_active?'active':'inactive')) }}</span></header>
                            <dl class="mt-4 grid grid-cols-3 gap-3 border-y border-[var(--pn-border)] py-4 text-sm"><div><dt class="text-xs text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.active_sessions') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ $branchSummary['active'] }}</dd></div><div><dt class="text-xs text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.attendance') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ $branchSummary['attendance'] }}</dd></div><div><dt class="text-xs text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.pending_payments') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ $branchSummary['pending'] }}</dd></div></dl>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><div><span class="block text-xs text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.cash_sales') }}</span>@forelse ($branchSummary['money'] as $currency=>$amount)<strong class="mt-1 block tabular-nums"><bdi dir="ltr">{{ $currency }} {{ number_format($amount/100,2) }}</bdi></strong>@empty<span class="mt-1 block text-sm text-[var(--pn-ink-muted)]">{{ __('actor_dashboard.no_sales') }}</span>@endforelse</div>@if($branch->is_active)<form method="POST" action="{{ route('branch-context.store',$branch) }}">@csrf<button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('actor_dashboard.open_branch') }}</button></form>@endif</div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-5">{{ $overview->links() }}</div>
            @endif
        </section>
    @elseif ($selectedBranch && $branches->count()>1)
        <aside class="mt-8 border-t border-[var(--pn-border)] pt-5" aria-labelledby="switch-heading"><h2 class="text-sm font-bold" id="switch-heading">{{ __('dashboard.switch_branch') }}</h2><div class="mt-3 flex flex-wrap gap-2">@foreach($branches as $branch) @unless($selectedBranch->is($branch))<form method="POST" action="{{ route('branch-context.store',$branch) }}">@csrf<button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit"><bdi dir="auto">{{ $branch->name }}</bdi></button></form>@endunless @endforeach</div></aside>
    @endif
</main>
@endsection
