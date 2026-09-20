@extends('layouts.app')

@section('title', __('audit.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
            <div>
                <h1 class="text-2xl font-bold">{{ __('audit.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('audit.page_description') }}</p>
            </div>
            @if ($canExport)
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold text-[var(--pn-primary)]" href="{{ route('audit.export', request()->query()) }}">{{ __('audit.export_csv') }}</a>
            @endif
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
            $subjectLabels = __('audit.subject_types');
        @endphp

        <section class="mt-8" aria-labelledby="filters-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" id="filters-heading">{{ __('audit.filters_heading') }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('audit.success_only') }}</p>
                </div>
            </div>
            <form class="mt-4 grid gap-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-6" method="GET" action="{{ route('audit.index') }}" data-pn-form>
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
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="actor_user_id" name="actor_user_id">
                        <option value="">{{ __('audit.all_actors') }}</option>
                        @foreach ($filterActors as $filterActor)
                            <option value="{{ $filterActor->id }}" @selected((string) request('actor_user_id') === (string) $filterActor->id)>{{ $filterActor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="branch_id">{{ __('audit.branch_filter') }}</label>
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="branch_id" name="branch_id">
                        <option value="">{{ __('audit.all_branches') }}</option>
                        @foreach ($filterBranches as $filterBranch)
                            <option value="{{ $filterBranch->id }}" @selected((string) request('branch_id') === (string) $filterBranch->id)>{{ $filterBranch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="subject_type">{{ __('audit.subject_filter') }}</label>
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="subject_type" name="subject_type">
                        <option value="">{{ __('audit.all_subjects') }}</option>
                        @foreach ($subjectLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('subject_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="date_from">{{ __('audit.date_from') }}</label>
                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="date_from" name="date_from" type="date" value="{{ request('date_from') }}">
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="date_to">{{ __('audit.date_to') }}</label>
                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="date_to" name="date_to" type="date" value="{{ request('date_to') }}">
                </div>
                <div class="flex flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-6">
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('audit.apply_filters') }}</button>
                    <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('audit.index') }}">{{ __('audit.clear_filters') }}</a>
                </div>
            </form>
        </section>

        <section class="mt-6" aria-labelledby="audit-table-heading">
            <h2 class="sr-only" id="audit-table-heading">{{ __('audit.table_caption') }}</h2>
            @if ($logs->isEmpty())
                <div class="rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                    <p class="font-semibold">{{ __('audit.no_logs') }}</p>
                    @if (request()->hasAny(['action', 'actor_user_id', 'branch_id', 'subject_type', 'date_from', 'date_to']))
                        <a class="mt-3 inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold text-[var(--pn-primary)] hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('audit.index') }}">{{ __('audit.clear_filters') }}</a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" data-pn-table data-pn-responsive-table role="region" aria-labelledby="audit-table-heading" tabindex="0">
                    <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                        <caption class="sr-only">{{ __('audit.table_caption') }}</caption>
                        <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                            <tr>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.occurred_at') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.event') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.branch') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.outcome') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('audit.details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($logs as $log)
                                @php
                                    $occurredAt = \Illuminate\Support\Carbon::parse($log->occurred_at, 'UTC')->utc();
                                    $localOccurredAt = $occurredAt->copy()->setTimezone($tenant->timezone ?: 'UTC');
                                    $actionLabel = is_array($actionLabels) && isset($actionLabels[$log->action]) ? $actionLabels[$log->action] : __('audit.unknown_action');
                                    $reasonLabel = is_array($reasonLabels) && isset($reasonLabels[$log->reason_code]) ? $reasonLabels[$log->reason_code] : __('audit.unknown_reason');
                                    $outcomeLabel = is_array($outcomeLabels) && isset($outcomeLabels[$log->outcome]) ? $outcomeLabels[$log->outcome] : __('audit.unknown_outcome');
                                    $beforeSnapshot = $beforeSnapshots->get($log->id, []);
                                    $afterSnapshot = $afterSnapshots->get($log->id, []);
                                    $subjectLabels = is_array($subjectLabels) ? $subjectLabels : [];
                                    $subjectLabel = $subjectLabels[$log->subject_type] ?? $log->subject_type;
                                @endphp
                                <tr class="align-top">
                                    <td class="px-4 py-3" data-label="{{ __('audit.occurred_at') }}">
                                        <time class="block whitespace-nowrap font-semibold tabular-nums" datetime="{{ $occurredAt->toIso8601String() }}" title="{{ __('audit.utc_time', ['time' => $occurredAt->format('Y-m-d H:i:s.u')]) }}" dir="ltr">{{ $localOccurredAt->format('Y-m-d H:i') }}</time>
                                        <span class="mt-1 block text-xs text-[var(--pn-ink-muted)]">{{ $localOccurredAt->format('T') }} · {{ __('audit.local_time') }}</span>
                                    </td>
                                    <td class="px-4 py-3" data-label="{{ __('audit.event') }}">
                                        <p class="font-semibold">{{ $actionLabel }}</p>
                                        <p class="mt-1 text-sm">{{ $actorLabels->get($log->actor_user_id, __('audit.unknown_actor')) }} <span class="text-[var(--pn-ink-muted)]">· {{ $subjectLabel }} <bdi dir="ltr">#{{ $log->subject_id }}</bdi></span></p>
                                    </td>
                                    <td class="px-4 py-3" data-label="{{ __('audit.branch') }}">{{ $log->branch_id === null ? __('audit.no_branch') : $branchLabels->get($log->branch_id, __('audit.unknown_branch')) }}</td>
                                    <td class="px-4 py-3" data-label="{{ __('audit.outcome') }}"><span class="inline-flex rounded-full bg-[var(--pn-success-soft)] px-3 py-1 text-sm font-semibold text-[var(--pn-success)]">{{ $outcomeLabel }}</span></td>
                                    <td class="px-4 py-3" data-label="{{ __('audit.details') }}">
                                        <details>
                                            <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold text-[var(--pn-primary)] outline-none hover:bg-[var(--pn-surface-subtle)] focus-visible:ring-2 focus-visible:ring-[var(--pn-focus)]">{{ __('audit.view_details') }}</summary>
                                            <div class="mt-3 max-w-2xl rounded-[10px] bg-[var(--pn-surface-subtle)] p-4">
                                                <p class="text-sm"><span class="font-semibold">{{ __('audit.reason') }}:</span> {{ $reasonLabel }}</p>
                                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                                    @foreach ([__('audit.before') => $beforeSnapshot, __('audit.after') => $afterSnapshot] as $snapshotTitle => $snapshot)
                                                        <section aria-label="{{ $snapshotTitle }}">
                                                            <h3 class="text-sm font-bold">{{ $snapshotTitle }}</h3>
                                                            @if (empty($snapshot))
                                                                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('audit.no_snapshot') }}</p>
                                                            @else
                                                                <dl class="mt-2 space-y-1 text-sm">
                                                                    @foreach ($snapshot as $key => $value)
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
                                                                        <div class="flex flex-wrap gap-x-2"><dt class="font-semibold">{{ is_array($snapshotLabels) && isset($snapshotLabels[$key]) ? $snapshotLabels[$key] : $key }}</dt><dd>{{ $displayValue }}</dd></div>
                                                                    @endforeach
                                                                </dl>
                                                            @endif
                                                        </section>
                                                    @endforeach
                                                </div>
                                                <p class="mt-4 border-t border-[var(--pn-border)] pt-3 text-xs text-[var(--pn-ink-muted)]">{{ __('audit.correlation_id') }}: <bdi dir="ltr">{{ $log->request_id }}</bdi></p>
                                            </div>
                                        </details>
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
