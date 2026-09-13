@extends('layouts.app')

@php
    $hasBranchContext = $selectedBranch !== null;
    $isTenantOwner = $tenant !== null && auth()->user()->can('view', $tenant);
    $assignment = $hasBranchContext
        ? auth()->user()->branches()->whereKey($selectedBranch->getKey())->first()?->pivot
        : null;
    $roleKey = $isTenantOwner ? 'owner' : (string) ($assignment?->role ?? 'staff');
    $roleLabel = __('dashboard.roles.'.$roleKey);
    if ($roleLabel === 'dashboard.roles.'.$roleKey) {
        $roleLabel = __('dashboard.roles.staff');
    }
    $actions = collect([
        [
            'route' => 'families.index',
            'label' => __('dashboard.actions.find_family'),
            'description' => __('dashboard.actions.find_family_description'),
            'visible' => app('router')->has('families.index') && auth()->user()->can('viewAny', \App\Models\Guardian::class),
        ],
        [
            'route' => 'tickets.index',
            'label' => __('dashboard.actions.issue_ticket'),
            'description' => __('dashboard.actions.issue_ticket_description'),
            'visible' => app('router')->has('tickets.index') && class_exists(\App\Models\Ticket::class) && auth()->user()->can('viewAny', \App\Models\Ticket::class),
        ],
        [
            'route' => 'sessions.index',
            'label' => __('dashboard.actions.view_sessions'),
            'description' => __('dashboard.actions.view_sessions_description'),
            'visible' => app('router')->has('sessions.index') && class_exists(\App\Models\PlaySession::class) && auth()->user()->can('viewAny', \App\Models\PlaySession::class),
        ],
    ])->filter(fn (array $action): bool => $action['visible'])->values();
@endphp

@section('title', __($hasBranchContext ? 'dashboard.workspace_title' : 'Branch context').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-7xl px-4 py-6 sm:px-6 lg:py-8" data-pn-dashboard>
        <header class="flex flex-wrap items-end justify-between gap-5 border-b border-[var(--pn-border)] pb-5">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $hasBranchContext ? __('dashboard.workspace_eyebrow') : __('dashboard.gate_eyebrow') }}</p>
                <h1 class="mt-1 text-2xl font-bold sm:text-[1.75rem]">{{ $hasBranchContext ? __('dashboard.workspace_title') : __('Branch context') }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">
                    {{ $hasBranchContext
                        ? __('dashboard.workspace_description', ['role' => $roleLabel, 'branch' => $selectedBranch->name])
                        : __('Signed in as :user', ['user' => auth()->user()->name]) }}
                </p>
            </div>
            @if ($hasBranchContext)
                <div class="flex shrink-0 items-center gap-3 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-4 py-3" aria-label="{{ __('dashboard.current_workspace') }}">
                    <span class="grid size-10 place-items-center rounded-[10px] bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]" aria-hidden="true">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('dashboard.current_workspace') }}</span>
                        <strong class="block max-w-56 truncate text-sm">{{ $selectedBranch->name }}</strong>
                    </span>
                </div>
            @endif
        </header>

        @if (! $hasBranchContext)
            <section class="mt-6 max-w-4xl" aria-labelledby="branches-heading" data-pn-branch-gate>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold" id="branches-heading">{{ __('dashboard.gate_title') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('dashboard.gate_description') }}</p>
                    </div>
                    <span class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ trans_choice('dashboard.branch_count', $branches->count(), ['count' => $branches->count()]) }}</span>
                </div>

                @if ($branches->isEmpty())
                    <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                        <p class="font-semibold">{{ __('No active branches are available.') }}</p>
                        <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('dashboard.gate_empty_description') }}</p>
                    </div>
                @else
                    <ul class="mt-4 grid list-none gap-3 p-0 sm:grid-cols-2" aria-label="{{ __('Available branches') }}">
                        @foreach ($branches as $branch)
                            <li>
                                <form method="POST" action="{{ route('branch-context.store', $branch) }}">
                                    @csrf
                                    <button class="flex min-h-16 w-full items-center justify-between gap-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-4 text-start hover:border-[var(--pn-border-strong)] hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">
                                        <span class="flex min-w-0 items-center gap-3">
                                            <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-[var(--pn-surface-subtle)] text-[var(--pn-primary)]" aria-hidden="true">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                                            </span>
                                            <span class="min-w-0">
                                                <strong class="block truncate">{{ $branch->name }}</strong>
                                                <small class="mt-1 block text-xs text-[var(--pn-ink-muted)]">{{ __('dashboard.open_workspace') }}</small>
                                            </span>
                                        </span>
                                        <svg class="size-5 shrink-0 text-[var(--pn-primary)] rtl:rotate-180" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @else
            <section class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(17rem,22rem)] lg:items-start">
                <div class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="quick-actions-heading">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold" id="quick-actions-heading">{{ __('dashboard.quick_actions') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('dashboard.quick_actions_description') }}</p>
                        </div>
                        <span class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ $roleLabel }}</span>
                    </div>

                    @if ($actions->isNotEmpty())
                        @php($primaryAction = $actions->first())
                        <a class="mt-5 flex min-h-20 items-center justify-between gap-4 rounded-[14px] bg-[var(--pn-primary)] px-5 py-4 text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" href="{{ route($primaryAction['route']) }}" data-pn-primary-action>
                            <span class="min-w-0">
                                <strong class="block text-base">{{ $primaryAction['label'] }}</strong>
                                <span class="mt-1 block text-sm leading-5 text-[color:oklch(0.97_0.007_205_/_0.85)]">{{ $primaryAction['description'] }}</span>
                            </span>
                            <svg class="size-6 shrink-0 rtl:rotate-180" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
                        </a>

                        @if ($actions->count() > 1)
                            <ul class="mt-3 grid list-none gap-3 p-0 sm:grid-cols-2" aria-label="{{ __('dashboard.more_actions') }}">
                                @foreach ($actions->skip(1) as $action)
                                    <li>
                                        <a class="flex min-h-16 items-center justify-between gap-3 rounded-[10px] border border-[var(--pn-border)] px-4 py-3 hover:border-[var(--pn-border-strong)] hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route($action['route']) }}">
                                            <span class="min-w-0">
                                                <strong class="block truncate text-sm">{{ $action['label'] }}</strong>
                                                <span class="mt-1 block text-xs leading-5 text-[var(--pn-ink-muted)]">{{ $action['description'] }}</span>
                                            </span>
                                            <svg class="size-5 shrink-0 text-[var(--pn-primary)] rtl:rotate-180" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <div class="mt-5 rounded-[10px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface-subtle)] p-4" role="status">
                            <p class="font-semibold">{{ __('dashboard.no_actions') }}</p>
                            <p class="mt-1 text-sm leading-5 text-[var(--pn-ink-muted)]">{{ __('dashboard.no_actions_description') }}</p>
                        </div>
                    @endif
                </div>

                <aside class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-5" aria-labelledby="workspace-context-heading">
                    <h2 class="text-base font-bold" id="workspace-context-heading">{{ __('dashboard.workspace_context') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('dashboard.branch_label') }}</dt>
                            <dd class="mt-1 font-semibold">{{ $selectedBranch->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('dashboard.access_label') }}</dt>
                            <dd class="mt-1 font-semibold">{{ $roleLabel }}</dd>
                        </div>
                    </dl>

                    @if ($branches->count() > 1)
                        <div class="mt-5 border-t border-[var(--pn-border)] pt-4">
                            <h3 class="text-sm font-bold">{{ __('dashboard.switch_branch') }}</h3>
                            <ul class="mt-2 space-y-1" aria-label="{{ __('dashboard.switch_branch') }}">
                                @foreach ($branches as $branch)
                                    @if (! $selectedBranch->is($branch))
                                        <li>
                                            <form method="POST" action="{{ route('branch-context.store', $branch) }}">
                                                @csrf
                                                <button class="flex min-h-11 w-full items-center justify-between gap-3 rounded-[10px] px-2 text-start text-sm font-semibold text-[var(--pn-primary)] hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">
                                                    <span class="truncate">{{ $branch->name }}</span>
                                                    <svg class="size-4 shrink-0 rtl:rotate-180" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </aside>
            </section>
        @endif
    </main>
@endsection
