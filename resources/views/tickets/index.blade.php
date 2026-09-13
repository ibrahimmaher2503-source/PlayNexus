@extends('layouts.app')

@section('title', __('tickets.page_title').' · PlayNexus')

@section('content')
    @php
        $branchList = collect($branches ?? []);
        $typeList = collect($types ?? []);
        $ticketList = collect($tickets ?? []);
        $assignmentList = collect($assignments ?? []);
        $scanGroups = collect($scansByTicketId ?? []);
        $issueFormContext = 'ticket-issue';
        $oldFormContext = old('form_context');
        $selectedBranch = $branchList->firstWhere('id', (int) ($selectedBranchId ?? 0));
        $selectedIssueBranchId = (string) ($oldFormContext === $issueFormContext ? old('branch_id', $selectedBranchId ?: $branchList->first()?->id) : ($selectedBranchId ?: $branchList->first()?->id));
        $selectedIssueBranch = $branchList->firstWhere('id', (int) $selectedIssueBranchId);
        $selectedIssueDate = $oldFormContext === $issueFormContext ? old('service_date', now($selectedIssueBranch?->timezone ?? config('app.timezone'))->toDateString()) : now($selectedIssueBranch?->timezone ?? config('app.timezone'))->toDateString();
        $familySearch = (string) ($familySearch ?? request()->query('family_q', ''));
        $manageableIds = collect($manageableBranchIds ?? [])->map(fn (mixed $id): int => (int) $id)->all();
        $formatEgp = static fn (mixed $minor): string => number_format(((int) $minor) / 100, 2, '.', ',');
        $statusClasses = [
            'issued' => 'border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]',
            'consumed' => 'border-[var(--pn-info)] bg-[var(--pn-info-soft)] text-[var(--pn-info)]',
            'expired' => 'border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] text-[var(--pn-warning)]',
            'cancelled' => 'border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] text-[var(--pn-danger)]',
        ];
        $scanClasses = [
            'accepted' => 'text-[var(--pn-success)]',
            'not_found' => 'text-[var(--pn-danger)]',
            'wrong_branch' => 'text-[var(--pn-danger)]',
            'cancelled' => 'text-[var(--pn-danger)]',
            'already_consumed' => 'text-[var(--pn-warning)]',
            'expired' => 'text-[var(--pn-warning)]',
            'wrong_service_date' => 'text-[var(--pn-warning)]',
            'family_unavailable' => 'text-[var(--pn-danger)]',
        ];
        $selectedFamily = $oldFormContext === $issueFormContext ? old('family_assignment') : null;
        $canIssue = $branchList->isNotEmpty() && $typeList->isNotEmpty() && $assignmentList->isNotEmpty();
        $activeTab = request()->query('tab');
        if (! in_array($activeTab, ['issue', 'validate', 'history'], true)) {
            $activeTab = $qrTicket ? 'issue' : ($oldFormContext === $issueFormContext ? 'issue' : 'validate');
        }
        $tabUrl = static fn (string $tab): string => route('tickets.index', array_filter([
            'tab' => $tab,
            'branch_id' => $selectedBranchId,
            'family_q' => $familySearch,
        ], static fn (mixed $value): bool => filled($value)));
        $panelAttributes = static fn (string $tab) => $activeTab === $tab ? '' : 'hidden aria-hidden="true"';
    @endphp

    <main class="mx-auto min-h-screen max-w-7xl px-4 py-6 sm:px-6" data-pn-tickets>
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('tickets.page_title') }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('tickets.page_description') }}</p>
        </header>

        <nav class="mt-5 overflow-x-auto border-b border-[var(--pn-border)]" aria-label="{{ __('tickets.tabs_label') }}" data-pn-ticket-tabs>
            <div class="flex min-w-max gap-1" role="tablist">
                @foreach (['issue' => __('tickets.tabs.issue'), 'validate' => __('tickets.tabs.validate'), 'history' => __('tickets.tabs.history')] as $tab => $label)
                    <a class="inline-flex min-h-11 items-center border-b-2 px-4 py-2 text-sm font-semibold {{ $activeTab === $tab ? 'border-[var(--pn-primary)] text-[var(--pn-primary)]' : 'border-transparent text-[var(--pn-ink-muted)] hover:border-[var(--pn-border-strong)] hover:text-[var(--pn-ink)]' }} focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-inset" href="{{ $tabUrl($tab) }}" role="tab" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}" aria-controls="ticket-tab-{{ $tab }}" @if ($activeTab === $tab) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </nav>

        @if (session('success') || session('status_message'))
            <p class="mt-6 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') ?? session('status_message') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="tickets-errors" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('tickets.validation_failed') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($errors->has('expected_version') || $errors->has('ticket') || session('conflict'))
            <div class="mt-4 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-4" id="tickets-conflict" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('tickets.conflict_title') }}</p>
                <p class="mt-1 text-sm leading-6">{{ session('conflict') ?? $errors->first('expected_version') ?? $errors->first('ticket') ?? __('tickets.conflict_description') }}</p>
            </div>
        @endif

        <div id="ticket-tab-validate" data-pn-ticket-panel="validate" {!! $panelAttributes('validate') !!}>
        @if ($scanResult)
            @php
                $scanAccepted = $scanResult === 'accepted';
            @endphp
            <section class="mt-6 rounded-[14px] border {{ $scanAccepted ? 'border-[var(--pn-success)] bg-[var(--pn-success-soft)]' : 'border-[var(--pn-danger)] bg-[var(--pn-danger-soft)]' }} p-5 sm:p-6" aria-labelledby="scan-result-heading" role="status" aria-live="polite">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold {{ $scanAccepted ? 'text-[var(--pn-success)]' : 'text-[var(--pn-danger)]' }}">{{ __('tickets.scan_result') }}</p>
                        <h2 class="mt-1 text-lg font-bold" id="scan-result-heading">{{ $scanAccepted ? __('tickets.scan_accepted') : __('tickets.scan_denied_title') }}</h2>
                        <p class="mt-1 text-sm leading-6">{{ data_get(__('tickets.scan_results'), $scanResult, __('tickets.scan_results.not_found')) }}</p>
                    </div>
                    <span class="inline-flex min-h-9 items-center rounded-full border {{ $scanAccepted ? 'border-[var(--pn-success)] text-[var(--pn-success)]' : 'border-[var(--pn-danger)] text-[var(--pn-danger)]' }} px-3 text-sm font-semibold"><span aria-hidden="true" class="me-2">{{ $scanAccepted ? '✓' : '!' }}</span>{{ data_get(__('tickets.scan_results'), $scanResult, __('tickets.scan_results.not_found')) }}</span>
                </div>
                @if ($scanAccepted && $scanTicket?->assignment_locked_at)
                    <p class="mt-4 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-3 text-sm font-semibold text-[var(--pn-ink)]">{{ __('tickets.scan_assignment_locked') }}</p>
                @elseif (! $scanAccepted)
                    <p class="mt-4 text-sm leading-6">{{ __('tickets.scan_recovery') }}</p>
                @endif
            </section>
        @endif

        <section class="mt-6 rounded-[14px] border-2 border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] p-5 sm:p-6" aria-labelledby="scan-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="mt-1 text-xl font-bold" id="scan-heading">{{ __('tickets.scan_heading') }}</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('tickets.scan_description') }}</p>
                </div>
                <span class="grid size-11 shrink-0 place-items-center rounded-[10px] border border-[var(--pn-primary)] bg-[var(--pn-surface)] text-[var(--pn-primary)]" aria-hidden="true">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M7 12h10M12 7v10"/></svg>
                </span>
            </div>
            <form class="mt-5 grid gap-4 lg:grid-cols-[minmax(12rem,18rem)_minmax(0,1fr)_auto] lg:items-end" method="POST" action="{{ route('tickets.scan') }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $scanKey }}">
                <div>
                    <label class="block text-sm font-semibold" for="scan-branch">{{ __('tickets.scan_branch_label') }}</label>
                    <select class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="scan-branch" name="branch_id" required>
                        <option value="">{{ __('tickets.branch_select_label') }}</option>
                        @foreach ($branchList as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branch->id === (string) ($selectedBranchId ?? ''))>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="scan-code">{{ __('tickets.scan_code_label') }}</label>
                    <input class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 font-mono tracking-wide focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="scan-code" name="code" type="text" value="{{ old('code') }}" placeholder="{{ __('tickets.scan_code_placeholder') }}" autocomplete="off" autofocus required aria-describedby="scan-code-help">
                    <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]" id="scan-code-help">{{ __('tickets.manual_code_hint') }}</p>
                </div>
                <button class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('tickets.scan_submit') }}</button>
            </form>
        </section>
        </div>

        @if ($qrPayload && $qrTicket)
            <div id="ticket-tab-issue-print" data-pn-ticket-panel="issue" {!! $panelAttributes('issue') !!}>
            <section class="pn-print-artifact mt-6 rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" id="ticket-print-artifact" data-pn-ticket-qr data-qr-payload="{{ $qrPayload }}" aria-labelledby="print-heading">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-4">
                    <div>
                        <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('tickets.print_heading') }}</p>
                        <h2 class="mt-1 text-lg font-bold" id="print-heading">{{ $qrTicket->type?->name ?? __('tickets.page_title') }}</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('tickets.print_instruction') }}</p>
                    </div>
                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 print:hidden" type="button" data-pn-print-ticket disabled>{{ __('tickets.print_action') }}</button>
                </div>
                @php
                    $qrStatusLabel = data_get(__('tickets.ticket_statuses'), $qrTicket->status, __('tickets.ticket_statuses.issued'));
                    $qrAssignmentLabel = $qrTicket->assignment_locked_at ? __('tickets.locked') : __('tickets.unlocked');
                @endphp
                <div class="mt-5 grid items-center gap-6 sm:grid-cols-[180px_minmax(0,1fr)]">
                    <div class="grid place-items-center rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3">
                        <canvas class="block aspect-square w-full max-w-full" width="320" height="320" data-pn-ticket-qr-canvas aria-label="{{ __('tickets.print_qr_label') }}"></canvas>
                        <p class="mt-2 hidden text-center text-xs text-[var(--pn-danger)]" data-pn-ticket-qr-fallback>{{ __('tickets.qr_fallback') }}</p>
                    </div>
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.ticket_identity') }}</dt><dd class="mt-1 font-mono text-lg font-bold" dir="ltr"><bdi>{{ $qrTicket->display_code }}</bdi></dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.ticket_branch') }}</dt><dd class="mt-1 font-semibold">{{ $qrTicket->branch?->name }}</dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.ticket_assignment') }}</dt><dd class="mt-1 font-semibold">{{ $qrTicket->child?->full_name }} <span class="font-normal text-[var(--pn-ink-muted)]">· {{ $qrTicket->guardian?->full_name }}</span></dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.ticket_price') }}</dt><dd class="mt-1 font-bold tabular-nums" dir="ltr"><bdi>{{ $formatEgp($qrTicket->price_minor) }} {{ $qrTicket->currency }}</bdi></dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.ticket_date') }}</dt><dd class="mt-1 font-bold tabular-nums" dir="ltr"><bdi>{{ $qrTicket->service_date?->format('Y-m-d') }}</bdi></dd></div>
                        @php
                            $qrTimezone = data_get($qrTicket, 'price_snapshot_json.branch_timezone') ?: ($qrTicket->branch?->timezone ?? config('app.timezone'));
                        @endphp
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.ticket_validity') }}</dt><dd class="mt-1 font-bold tabular-nums" dir="ltr"><bdi>{{ $qrTicket->valid_from?->setTimezone($qrTimezone)->format('H:i') }} – {{ $qrTicket->valid_until?->setTimezone($qrTimezone)->format('H:i') }}</bdi></dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.status_column') }}</dt><dd class="mt-1 font-bold">{{ $qrStatusLabel }}</dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.locked') }}</dt><dd class="mt-1 font-bold">{{ $qrAssignmentLabel }}</dd></div>
                    </dl>
                </div>
                <p class="mt-4 text-xs text-[var(--pn-ink-muted)] print:hidden">{{ __('tickets.print_close_hint') }} {{ __('tickets.reprint_notice') }}</p>
            </section>
            </div>
        @endif

        <div id="ticket-tab-history" data-pn-ticket-panel="history" {!! $panelAttributes('history') !!}>
        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="ticket-filters-heading">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('tickets.branch_context') }}</p>
                    <h2 class="mt-1 text-lg font-bold" id="ticket-filters-heading">{{ $selectedBranch?->name ?? __('tickets.branch_select_label') }}</h2>
                </div>
                @if ($selectedBranch)
                    <span class="inline-flex min-h-9 items-center rounded-full border border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] px-3 text-sm font-semibold text-[var(--pn-primary)]"><bdi dir="ltr">{{ $selectedBranch->code ?? '—' }}</bdi></span>
                @endif
            </div>
            <form class="mt-5 grid gap-4 lg:grid-cols-[minmax(12rem,18rem)_minmax(0,1fr)_auto] lg:items-end" method="GET" action="{{ route('tickets.index') }}" role="search">
                <div>
                    <label class="block text-sm font-semibold" for="ticket-filter-branch">{{ __('tickets.branch_select_label') }}</label>
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="ticket-filter-branch" name="branch_id">
                        <option value="">{{ __('tickets.all_branches') }}</option>
                        @foreach ($branchList as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branch->id === (string) ($selectedBranchId ?? ''))>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="family-q">{{ __('tickets.family_search_label') }}</label>
                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="family-q" name="family_q" type="search" value="{{ $familySearch }}" placeholder="{{ __('tickets.family_search_placeholder') }}" maxlength="100" autocomplete="off" dir="auto" aria-describedby="family-q-help">
                    <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]" id="family-q-help">{{ __('tickets.family_search_hint') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('tickets.apply_filter') }}</button>
                    @if ($selectedBranchId || filled($familySearch))
                        <a class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('tickets.index') }}">{{ __('tickets.clear_filter') }}</a>
                    @endif
                </div>
            </form>
        </section>
        </div>

        <div id="ticket-tab-issue" data-pn-ticket-panel="issue" {!! $panelAttributes('issue') !!}>
        <div class="mt-8 max-w-4xl">
            <section aria-labelledby="issue-heading">
                <div>
                    <h2 class="text-xl font-bold" id="issue-heading">{{ __('tickets.issue_heading') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('tickets.issue_description') }}</p>
                </div>

                @if (! $branchList->isNotEmpty())
                    <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                        <p class="font-semibold">{{ __('tickets.issue_no_branches') }}</p>
                    </div>
                @elseif (! $typeList->isNotEmpty())
                    <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                        <p class="font-semibold">{{ __('tickets.issue_no_types') }}</p>
                    </div>
                @elseif (! $assignmentList->isNotEmpty())
                    <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                        <p class="font-semibold">{{ __('tickets.issue_no_families') }}</p>
                    </div>
                @else
                    <form class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" method="POST" action="{{ route('tickets.issue') }}" aria-describedby="issue-help {{ $errors->any() ? 'tickets-errors' : '' }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $issueKey }}">
                        <input type="hidden" name="form_context" value="{{ $issueFormContext }}">
                        <p class="sr-only" id="issue-help">{{ __('tickets.issue_description') }}</p>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold" for="issue-branch">{{ __('tickets.issue_branch_label') }}</label>
                                <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="issue-branch" name="branch_id" required data-pn-ticket-branch-select>
                                    @foreach ($branchList as $branch)
                                        <option value="{{ $branch->id }}" data-local-today="{{ now($branch->timezone ?? config('app.timezone'))->toDateString() }}" @selected((string) $branch->id === $selectedIssueBranchId)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="issue-type">{{ __('tickets.issue_type_label') }}</label>
                                <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="issue-type" name="ticket_type_id" required data-pn-ticket-type-select>
                                    <option value="">{{ __('tickets.issue_type_placeholder') }}</option>
                                    @foreach ($typeList as $type)
                                        @php
                                            $pricingRule = $type->pricingRule;
                                            $typeAvailable = $pricingRule !== null;
                                        @endphp
                                        <option value="{{ $type->id }}" data-branch-id="{{ $type->branch_id }}" data-unavailable="{{ $typeAvailable ? 'false' : 'true' }}" dir="auto" @disabled(! $typeAvailable) @selected($oldFormContext === $issueFormContext && (string) old('ticket_type_id') === (string) $type->id)>{{ $type->name }} · {{ __('tickets.type_price', ['price' => $formatEgp($type->price_minor)]) }}{{ $typeAvailable ? '' : ' · '.__('tickets.type_unavailable') }}</option>
                                    @endforeach
                                </select>
                                @error('ticket_type_id')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold" for="issue-family">{{ __('tickets.issue_family_label') }}</label>
                                <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="issue-family" name="family_assignment" required>
                                    <option value="">{{ __('tickets.issue_family_placeholder') }}</option>
                                    @foreach ($assignmentList as $assignment)
                                        @php
                                            $assignmentValue = $assignment->guardian_id.':'.$assignment->child_id;
                                        @endphp
                                        <option value="{{ $assignmentValue }}" dir="auto" @selected($oldFormContext === $issueFormContext && (string) $selectedFamily === (string) $assignmentValue)>{{ $assignment->child_name }} · {{ $assignment->guardian_name }} · {{ $assignment->phone_masked }}</option>
                                    @endforeach
                                </select>
                                @error('family_assignment')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="issue-date">{{ __('tickets.issue_date_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="issue-date" name="service_date" type="date" value="{{ $selectedIssueDate }}" required aria-describedby="issue-date-help" data-pn-ticket-service-date>
                                <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]" id="issue-date-help">{{ __('tickets.issue_date_hint') }}</p>
                                @error('service_date')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('tickets.issue_submit') }}</button>
                    </form>
                @endif
            </section>

        </div>

        <p class="mt-4 text-sm text-[var(--pn-ink-muted)]">{{ __('tickets.type_setup_in_pricing') }} <a class="font-semibold text-[var(--pn-primary)] underline underline-offset-2 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('pricing.index', ['tab' => 'types', 'branch_id' => $selectedBranchId]) }}">{{ __('tickets.open_pricing') }}</a></p>
        </div>

        <div id="ticket-history-list" data-pn-ticket-panel="history" {!! $panelAttributes('history') !!}>
        <section class="mt-10" aria-labelledby="tickets-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold" id="tickets-heading">{{ __('tickets.tickets_heading') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('tickets.tickets_description') }}</p>
                </div>
                <span class="text-sm font-semibold text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $ticketList->count() }}</bdi></span>
            </div>

            @if ($ticketList->isEmpty())
                <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                    <p class="font-semibold">{{ $selectedBranchId || filled($familySearch) ? __('tickets.no_tickets') : __('tickets.no_tickets_empty') }}</p>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('tickets.no_tickets_hint') }}</p>
                </div>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="tickets-heading" tabindex="0">
                    <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                        <caption class="sr-only">{{ __('tickets.tickets_table_caption') }}</caption>
                        <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.ticket_column') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.family_column') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.type_column') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.service_date_column') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.valid_until_column') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.status_column') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.last_scan_column') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-start" scope="col">{{ __('tickets.actions_column') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($ticketList as $ticket)
                                @php
                                    $ticketStatus = (string) $ticket->status;
                                    $ticketScans = collect($scanGroups->get($ticket->id, []))->sortByDesc(fn (mixed $scan): mixed => data_get($scan, 'scanned_at'))->values();
                                    $latestScan = $ticketScans->first();
                                    $assignmentLocked = $ticket->assignment_locked_at !== null || $ticket->consumed_at !== null || (int) $ticket->uses_count > 0;
                                    $canCorrect = $ticketStatus === 'issued' && ! $assignmentLocked;
                                    $canCancel = $canCorrect && in_array((int) $ticket->branch_id, $manageableIds, true);
                                    $ticketTimezone = data_get($ticket, 'price_snapshot_json.branch_timezone') ?: ($ticket->branch?->timezone ?? config('app.timezone'));
                                    $statusLabel = data_get(__('tickets.ticket_statuses'), $ticketStatus, __('tickets.ticket_statuses.issued'));
                                    $latestResult = data_get($latestScan, 'result');
                                @endphp
                                <tr class="align-top">
                                    <th class="min-w-44 px-4 py-4 text-start" scope="row" data-label="{{ __('tickets.ticket_column') }}">
                                        <span class="block font-mono text-lg font-bold" dir="ltr"><bdi>{{ $ticket->display_code }}</bdi></span>
                                        <span class="mt-1 block text-sm text-[var(--pn-ink-muted)]">{{ $ticket->branch?->name }}</span>
                                    </th>
                                    <td class="min-w-48 px-4 py-4" data-label="{{ __('tickets.family_column') }}">
                                        <span class="block font-semibold">{{ $ticket->child?->full_name }}</span>
                                        <span class="mt-1 block text-sm text-[var(--pn-ink-muted)]">{{ $ticket->guardian?->full_name }}</span>
                                    </td>
                                    <td class="min-w-40 px-4 py-4" data-label="{{ __('tickets.type_column') }}">
                                        <span class="block font-semibold">{{ $ticket->type?->name }}</span>
                                        <span class="mt-1 block text-sm tabular-nums text-[var(--pn-ink-muted)]" dir="ltr"><bdi>{{ $formatEgp($ticket->price_minor) }} {{ $ticket->currency }}</bdi></span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 tabular-nums" data-label="{{ __('tickets.service_date_column') }}" dir="ltr"><bdi>{{ $ticket->service_date?->format('Y-m-d') }}</bdi></td>
                                    <td class="whitespace-nowrap px-4 py-4 tabular-nums" data-label="{{ __('tickets.valid_until_column') }}" dir="ltr"><bdi>{{ $ticket->valid_until?->setTimezone($ticketTimezone)->format('Y-m-d H:i') }}</bdi></td>
                                    <td class="whitespace-nowrap px-4 py-4" data-label="{{ __('tickets.status_column') }}">
                                        <span class="inline-flex min-h-8 items-center rounded-full border px-3 text-sm font-semibold {{ $statusClasses[$ticketStatus] ?? 'border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]' }}">{{ $statusLabel }}</span>
                                        @if ($assignmentLocked)
                                            <span class="mt-2 block text-xs font-semibold text-[var(--pn-warning)]">{{ __('tickets.locked') }}</span>
                                        @elseif ($ticketStatus === 'issued')
                                            <span class="mt-2 block text-xs text-[var(--pn-ink-muted)]">{{ __('tickets.unlocked') }}</span>
                                        @endif
                                    </td>
                                    <td class="min-w-40 px-4 py-4" data-label="{{ __('tickets.last_scan_column') }}">
                                        @if ($latestScan)
                                            <span class="font-semibold {{ $scanClasses[$latestResult] ?? 'text-[var(--pn-ink)]' }}">{{ data_get(__('tickets.scan_results'), $latestResult, __('tickets.not_scanned')) }}</span>
                                            @if (data_get($latestScan, 'scanned_at'))
                                                <span class="mt-1 block text-xs tabular-nums text-[var(--pn-ink-muted)]" dir="ltr"><bdi>{{ data_get($latestScan, 'scanned_at')->setTimezone($ticketTimezone)->format('Y-m-d H:i') }}</bdi></span>
                                            @endif
                                        @else
                                            <span class="text-sm text-[var(--pn-ink-muted)]">{{ __('tickets.not_scanned') }}</span>
                                        @endif
                                    </td>
                                    <td class="min-w-64 px-4 py-4" data-label="{{ __('tickets.actions_column') }}">
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('tickets.reprint', $ticket) }}">
                                                @csrf
                                                <input type="hidden" name="expected_version" value="{{ $ticket->lock_version }}">
                                                <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('tickets.reprint') }}</button>
                                            </form>
                                        </div>
                                        @if ($canCorrect)
                                            <details class="group mt-3 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)]">
                                                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]"><span>{{ __('tickets.reassign_summary') }}</span><span aria-hidden="true" class="text-lg leading-none transition-transform group-open:rotate-45">+</span></summary>
                                                <div class="border-t border-[var(--pn-border)] p-3">
                                                    <p class="text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('tickets.reassign_description') }}</p>
                                                    <form class="mt-3 space-y-3" method="POST" action="{{ route('tickets.reassign', $ticket) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="form_context" value="reassign-{{ $ticket->id }}">
                                                        <input type="hidden" name="expected_version" value="{{ $ticket->lock_version }}">
                                                        <label class="block text-xs font-semibold" for="reassign-{{ $ticket->id }}">{{ __('tickets.reassign_family_label') }}</label>
                                                        <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="reassign-{{ $ticket->id }}" name="family_assignment" required>
                                                            @foreach ($assignmentList as $assignment)
                                                                @php
                                                                    $assignmentValue = $assignment->guardian_id.':'.$assignment->child_id;
                                                                @endphp
                                                                <option value="{{ $assignmentValue }}" dir="auto" @selected((int) $ticket->guardian_id === (int) $assignment->guardian_id && (int) $ticket->child_id === (int) $assignment->child_id)>{{ $assignment->child_name }} · {{ $assignment->guardian_name }} · {{ $assignment->phone_masked }}</option>
                                                            @endforeach
                                                        </select>
                                                        <label class="block text-xs font-semibold" for="reassign-reason-{{ $ticket->id }}">{{ __('tickets.reassign_reason_label') }}</label>
                                                        <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="reassign-reason-{{ $ticket->id }}" name="reason" required>
                                                            @foreach (__('tickets.reassign_reason') as $reason => $reasonLabel)<option value="{{ $reason }}">{{ $reasonLabel }}</option>@endforeach
                                                        </select>
                                                        <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-3 text-sm font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('tickets.reassign_submit') }}</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @endif
                                        @if ($canCancel)
                                            <details class="group mt-3 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)]">
                                                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]"><span>{{ __('tickets.cancel_summary') }}</span><span aria-hidden="true" class="text-lg leading-none transition-transform group-open:rotate-45">+</span></summary>
                                                <div class="border-t border-[var(--pn-danger)] p-3">
                                                    <p class="text-xs leading-5 text-[var(--pn-ink)]">{{ __('tickets.cancel_description') }}</p>
                                                    <p class="mt-2 text-xs font-semibold text-[var(--pn-danger)]">{{ __('tickets.cancel_consequence') }}</p>
                                                    <form class="mt-3 space-y-3" method="POST" action="{{ route('tickets.cancel', $ticket) }}">
                                                        @csrf
                                                        <input type="hidden" name="expected_version" value="{{ $ticket->lock_version }}">
                                                        <label class="block text-xs font-semibold" for="cancel-reason-{{ $ticket->id }}">{{ __('tickets.cancel_reason_label') }}</label>
                                                        <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] px-2 text-sm focus:border-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="cancel-reason-{{ $ticket->id }}" name="reason" required>
                                                            @foreach (__('tickets.cancel_reason') as $reason => $reasonLabel)<option value="{{ $reason }}">{{ $reasonLabel }}</option>@endforeach
                                                        </select>
                                                        <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] border border-[var(--pn-danger)] px-3 text-sm font-semibold text-[var(--pn-danger)] hover:bg-[var(--pn-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('tickets.cancel_submit') }}</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @elseif ($canCorrect)
                                            <p class="mt-3 text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('tickets.manager_only_cancel') }}</p>
                                        @endif
                                        <details class="mt-3 rounded-[10px] border border-[var(--pn-border)]">
                                            <summary class="flex min-h-11 cursor-pointer items-center px-3 py-2 text-sm font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]">{{ __('tickets.history_summary', ['count' => $ticketScans->count()]) }}</summary>
                                            <div class="border-t border-[var(--pn-border)] p-3">
                                                @if ($ticketScans->isEmpty())
                                                    <p class="text-xs text-[var(--pn-ink-muted)]">{{ __('tickets.history_empty') }}</p>
                                                @else
                                                    <ul class="space-y-2 text-xs">
                                                        @foreach ($ticketScans as $scan)
                                                            <li class="flex items-start justify-between gap-3"><span class="font-semibold {{ $scanClasses[data_get($scan, 'result')] ?? 'text-[var(--pn-ink)]' }}">{{ data_get(__('tickets.scan_results'), data_get($scan, 'result'), __('tickets.not_scanned')) }}</span><bdi class="tabular-nums text-[var(--pn-ink-muted)]" dir="ltr">{{ data_get($scan, 'scanned_at')?->setTimezone($ticketTimezone)->format('Y-m-d H:i') }}</bdi></li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        @endif
        </section>
        </div>
    </main>
@endsection
