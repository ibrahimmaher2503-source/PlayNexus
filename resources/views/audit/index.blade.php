@extends('layouts.app')

@section('title', __('audit.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-7xl px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
            <div>
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}">{{ __('audit.back_to_dashboard') }}</a>
                <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
                <h1 class="mt-1 text-2xl font-bold">{{ __('audit.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('audit.page_description') }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <form class="flex items-end gap-2" method="POST" action="{{ route('locale.store') }}">
                    @csrf
                    <label class="text-sm font-semibold" for="locale">{{ __('Language') }}</label>
                    <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="locale" name="locale">
                        <option value="en" @selected(app()->isLocale('en'))>{{ __('English') }}</option>
                        <option value="ar" @selected(app()->isLocale('ar'))>{{ __('Arabic') }}</option>
                    </select>
                    <button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-3 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('Change') }}</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('Sign out') }}</button>
                </form>
            </div>
        </header>

        @if ($errors->any())
            <div class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] p-4 text-[var(--pn-danger)]" id="audit-filter-errors" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('audit.errors.heading') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $actionLabels = __('audit.actions');
            $outcomeLabels = __('audit.outcomes');
            $reasonLabels = __('audit.reasons');
            $snapshotLabels = __('audit.snapshot_key');
            $snapshotStatuses = __('audit.snapshot_status');
            $snapshotRoles = __('audit.snapshot_role');
            $snapshotBooleans = __('audit.snapshot_boolean');
        @endphp

        <section class="mt-8" aria-labelledby="filters-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" id="filters-heading">{{ __('audit.filters_heading') }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('audit.success_only') }}</p>
                </div>
            </div>
            <form class="mt-4 grid gap-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4" method="GET" action="{{ route('audit.index') }}">
                <div>
                    <label class="block text-sm font-semibold" for="action">{{ __('audit.action_filter') }}</label>
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="action" name="action">
                        <option value="">{{ __('audit.all_actions') }}</option>
                        @foreach ($actionLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="actor_user_id">{{ __('audit.actor_filter') }}</label>
                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="actor_user_id" name="actor_user_id" type="number" min="1" step="1" inputmode="numeric" placeholder="{{ __('audit.actor_placeholder') }}" value="{{ request('actor_user_id') }}">
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="outcome">{{ __('audit.outcome_filter') }}</label>
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="outcome" name="outcome">
                        <option value="success" @selected(request('outcome', 'success') === 'success')>{{ is_array($outcomeLabels) ? $outcomeLabels['success'] : __('audit.success_only') }}</option>
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('audit.apply_filters') }}</button>
                    <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('audit.index') }}">{{ __('audit.clear_filters') }}</a>
                </div>
            </form>
        </section>

        <section class="mt-8" aria-labelledby="audit-table-heading">
            <h2 class="sr-only" id="audit-table-heading">{{ __('audit.table_caption') }}</h2>
            @if ($logs->isEmpty())
                <p class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('audit.no_logs') }}</p>
            @else
                <div class="overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="audit-table-heading" tabindex="0">
                    <table class="min-w-[1100px] divide-y divide-[var(--pn-border)] text-start">
                        <caption class="sr-only">{{ __('audit.table_caption') }}</caption>
                        <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                            <tr>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.occurred_at') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.actor') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.branch') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.action') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.subject') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.outcome') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.reason') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.before') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.after') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($logs as $log)
                                @php
                                    $occurredAt = \Illuminate\Support\Carbon::parse($log->occurred_at, 'UTC')->utc();
                                    $actionLabel = is_array($actionLabels) && isset($actionLabels[$log->action]) ? $actionLabels[$log->action] : __('audit.unknown_action');
                                    $reasonLabel = is_array($reasonLabels) && isset($reasonLabels[$log->reason_code]) ? $reasonLabels[$log->reason_code] : __('audit.unknown_reason');
                                    $outcomeLabel = is_array($outcomeLabels) && isset($outcomeLabels[$log->outcome]) ? $outcomeLabels[$log->outcome] : __('audit.unknown_outcome');
                                    $beforeSnapshot = $beforeSnapshots->get($log->id, []);
                                    $afterSnapshot = $afterSnapshots->get($log->id, []);
                                @endphp
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <time datetime="{{ $occurredAt->toIso8601String() }}" dir="ltr">{{ $occurredAt->format('Y-m-d H:i:s.u') }} UTC</time>
                                    </td>
                                    <td class="px-4 py-4 font-semibold">{{ $actorLabels->get($log->actor_user_id, __('audit.unknown_actor')) }} <span class="font-normal text-[var(--pn-ink-muted)]">#<bdi dir="ltr">{{ $log->actor_user_id }}</bdi></span></td>
                                    <td class="px-4 py-4">{{ $log->branch_id === null ? __('audit.no_branch') : $branchLabels->get($log->branch_id, __('audit.unknown_branch')) }}</td>
                                    <td class="px-4 py-4 font-semibold">{{ $actionLabel }}</td>
                                    <td class="px-4 py-4"><bdi dir="ltr">{{ $log->subject_type }} #{{ $log->subject_id }}</bdi></td>
                                    <td class="px-4 py-4">{{ $outcomeLabel }}</td>
                                    <td class="px-4 py-4">{{ $reasonLabel }}</td>
                                    <td class="min-w-48 px-4 py-4">
                                        @if (empty($beforeSnapshot))
                                            <span class="text-sm text-[var(--pn-ink-muted)]">{{ __('audit.no_snapshot') }}</span>
                                        @else
                                            <dl class="space-y-1 text-sm">
                                                @foreach ($beforeSnapshot as $key => $value)
                                                    @php
                                                        $displayValue = $value;
                                                        if ($key === 'is_active' && is_bool($value)) {
                                                            $displayValue = $snapshotBooleans[$value ? 'true' : 'false'] ?? $value;
                                                        } elseif ($key === 'status' && is_array($snapshotStatuses) && isset($snapshotStatuses[$value])) {
                                                            $displayValue = $snapshotStatuses[$value];
                                                        } elseif ($key === 'role' && is_array($snapshotRoles) && isset($snapshotRoles[$value])) {
                                                            $displayValue = $snapshotRoles[$value];
                                                        }
                                                    @endphp
                                                    <div class="flex gap-2"><dt class="font-semibold">{{ is_array($snapshotLabels) && isset($snapshotLabels[$key]) ? $snapshotLabels[$key] : $key }}</dt><dd>{{ $displayValue }}</dd></div>
                                                @endforeach
                                            </dl>
                                        @endif
                                    </td>
                                    <td class="min-w-48 px-4 py-4">
                                        @if (empty($afterSnapshot))
                                            <span class="text-sm text-[var(--pn-ink-muted)]">{{ __('audit.no_snapshot') }}</span>
                                        @else
                                            <dl class="space-y-1 text-sm">
                                                @foreach ($afterSnapshot as $key => $value)
                                                    @php
                                                        $displayValue = $value;
                                                        if ($key === 'is_active' && is_bool($value)) {
                                                            $displayValue = $snapshotBooleans[$value ? 'true' : 'false'] ?? $value;
                                                        } elseif ($key === 'status' && is_array($snapshotStatuses) && isset($snapshotStatuses[$value])) {
                                                            $displayValue = $snapshotStatuses[$value];
                                                        } elseif ($key === 'role' && is_array($snapshotRoles) && isset($snapshotRoles[$value])) {
                                                            $displayValue = $snapshotRoles[$value];
                                                        }
                                                    @endphp
                                                    <div class="flex gap-2"><dt class="font-semibold">{{ is_array($snapshotLabels) && isset($snapshotLabels[$key]) ? $snapshotLabels[$key] : $key }}</dt><dd>{{ $displayValue }}</dd></div>
                                                @endforeach
                                            </dl>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($logs->hasPages() || $logs->currentPage() > 1)
                <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('audit.pagination_label') }}">
                    <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('audit.page_position', ['current' => $logs->currentPage(), 'last' => $logs->lastPage()]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($logs->previousPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $logs->previousPageUrl() }}" aria-label="{{ __('audit.previous_page') }}">{{ __('audit.previous') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('audit.previous') }}</span>
                        @endif
                        @if ($logs->nextPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $logs->nextPageUrl() }}" aria-label="{{ __('audit.next_page') }}">{{ __('audit.next') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('audit.next') }}</span>
                        @endif
                    </div>
                </nav>
            @endif
        </section>
    </main>
@endsection
