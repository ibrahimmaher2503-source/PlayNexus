@extends('layouts.app')

@section('title', __('sessions.page_title').' · PlayNexus')

@section('content')
    @php
        $branchList = collect($branches ?? []);
        $sessionList = $sessions ?? collect([]);
        $filterQuery = (string) data_get($filters ?? [], 'q', request()->query('q', ''));
        $filterStatus = (string) data_get($filters ?? [], 'status', request()->query('status', 'active'));
        $filterBranchId = (string) request()->query('branch_id', $selectedBranchId ?? '');
        $checkoutErrorSessionId = (string) old('checkout_session_id', '');
        $selectedBranch = $branchList->firstWhere('id', (int) ($selectedBranchId ?? 0));
        $checkInBranchIds = collect($checkInBranchIds ?? [])->map(static fn (mixed $id): int => (int) $id)->filter(static fn (int $id): bool => $id > 0)->values()->all();
        $canCheckIn = $checkInBranchIds !== [];
        $checkInBranchId = (int) old('branch_id', $selectedBranchId ?: ($checkInBranchIds[0] ?? $branchList->first()?->id ?? 0));
        if (! in_array($checkInBranchId, $checkInBranchIds, true)) {
            $checkInBranchId = $checkInBranchIds[0] ?? 0;
        }
        $managerBoardFirst = (bool) ($managerBoardFirst ?? false);
        $occupancyList = collect($branchOccupancy ?? []);
        $occupancyFor = static function (mixed $branchId) use ($occupancyList): mixed {
            return $occupancyList->get($branchId) ?? $occupancyList->get((string) $branchId) ?? [];
        };
        $occupancyBranchId = $selectedBranchId ?: (count($branchList) === 1 ? $branchList->first()?->id : null);
        $selectedOccupancy = $occupancyBranchId === null ? [] : $occupancyFor($occupancyBranchId);
        $selectedUsed = (int) data_get($selectedOccupancy, 'active_count', 0);
        $selectedCapacity = data_get($selectedOccupancy, 'capacity');
        $serverClock = $serverNow ?? now('UTC');
        $statusClasses = [
            'active' => 'border-[var(--pn-success)] bg-[var(--pn-success-soft)] text-[var(--pn-success)]',
            'paused' => 'border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] text-[var(--pn-warning)]',
            'pending_payment' => 'border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] text-[var(--pn-warning)]',
            'completed' => 'border-[var(--pn-info)] bg-[var(--pn-info-soft)] text-[var(--pn-info)]',
            'cancelled' => 'border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] text-[var(--pn-danger)]',
        ];
        $formatMoney = static function (mixed $minor, mixed $currency): string {
            $value = max(0, (int) $minor);

            return number_format(intdiv($value, 100), 0, '.', ',').'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT).' '.(string) $currency;
        };
        $formatTaxRate = static fn (mixed $basisPoints): string => rtrim(rtrim(number_format(((int) $basisPoints) / 100, 2, '.', ''), '0'), '.').'%';
        $paginationQuery = array_filter([
            'branch_id' => $filterBranchId,
            'q' => $filterQuery,
            'status' => request()->has('status') ? $filterStatus : '',
        ], static fn (mixed $value): bool => filled($value));
        if (is_object($sessionList) && method_exists($sessionList, 'appends')) {
            $sessionList->appends($paginationQuery);
        }
    @endphp

    <main class="mx-auto min-h-screen max-w-7xl px-4 py-6 sm:px-6" data-pn-sessions>
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold" id="sessions-heading">{{ __('sessions.page_title') }}</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.page_description') }}</p>
        </header>

        @if (session('success') || session('status_message'))
            <p class="mt-6 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') ?? session('status_message') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="sessions-errors" role="alert" aria-live="assertive" tabindex="-1" data-pn-focus-on-load>
                <p class="font-semibold">{{ $checkoutErrorSessionId !== '' ? __('sessions.checkout.validation_failed') : __('sessions.validation_failed') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('conflict') || $errors->has('expected_version') || $errors->has('idempotency_key') || $errors->has('check_in'))
            <div class="mt-4 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-4" id="sessions-conflict" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('sessions.conflict_title') }}</p>
                <p class="mt-1 text-sm leading-6">{{ session('conflict') ?? $errors->first('expected_version') ?? $errors->first('idempotency_key') ?? $errors->first('check_in') ?? __('sessions.conflict_description') }}</p>
            </div>
        @endif

        @if (session('denied') || session('authorization_denied'))
            <div class="mt-4 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="sessions-denied" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('sessions.denied_title') }}</p>
                <p class="mt-1 text-sm leading-6">{{ session('denied') ?? session('authorization_denied') ?? __('sessions.denied_description') }}</p>
            </div>
        @endif

        @php
            $checkInResult = (string) session('session_checkin_result', '');
            $checkInResultAccepted = $checkInResult === 'accepted';
            $checkInResultConflict = in_array($checkInResult, ['active_session_exists', 'capacity_full'], true);
            $checkInResultLabel = data_get(__('sessions.check_in_results'), $checkInResult, __('sessions.check_in_no_change'));
        @endphp
        @if ($checkInResult !== '')
            <section class="mt-4 rounded-[10px] border {{ $checkInResultAccepted ? 'border-[var(--pn-success)] bg-[var(--pn-success-soft)] text-[var(--pn-success)]' : ($checkInResultConflict ? 'border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] text-[var(--pn-ink)]' : 'border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] text-[var(--pn-danger)]') }} p-4" id="sessions-check-in-result" role="{{ $checkInResultAccepted ? 'status' : 'alert' }}" aria-live="polite" tabindex="-1" data-pn-focus-on-load>
                <p class="font-semibold">{{ $checkInResultAccepted ? __('sessions.check_in_success') : ($checkInResultConflict ? __('sessions.check_in_conflict_heading') : __('sessions.check_in_denied_heading')) }}</p>
                <p class="mt-1 text-sm leading-6">{{ $checkInResultLabel }}</p>
                @if (! $checkInResultAccepted)
                    <p class="mt-2 text-sm leading-6">{{ __('sessions.check_in_no_change') }}</p>
                @endif
            </section>
        @endif

        <div class="mt-6 flex flex-col" data-pn-session-flow data-pn-session-layout="{{ $managerBoardFirst ? 'manager' : ($canCheckIn ? 'scan' : 'read-only') }}">
        <section class="{{ $managerBoardFirst ? 'order-1' : 'order-2' }} rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" data-pn-session-filters aria-labelledby="session-branch-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.branch_context') }}</p>
                    <h2 class="mt-1 text-lg font-bold" id="session-branch-heading">{{ $selectedBranch?->name ?? __('sessions.all_branches') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.branch_context_description') }}</p>
                </div>
                <div class="rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] px-4 py-3 text-start">
                    <p class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.occupancy') }}</p>
                    @if (($selectedBranchId || count($branchList) === 1) && $selectedCapacity !== null)
                        <p class="mt-1 font-bold tabular-nums"><bdi dir="ltr">{{ __('sessions.occupancy_value', ['used' => $selectedUsed, 'capacity' => $selectedCapacity]) }}</bdi></p>
                    @elseif (! $selectedBranchId && count($branchList) > 1)
                        <p class="mt-1 text-sm font-semibold">{{ __('sessions.occupancy_choose_branch') }}</p>
                    @else
                        <p class="mt-1 text-sm font-semibold">{{ __('sessions.occupancy_unknown') }}</p>
                    @endif
                </div>
            </div>
            @if ($branchList->isNotEmpty())
                <form class="mt-5 grid gap-4 xl:grid-cols-[minmax(12rem,18rem)_minmax(12rem,1fr)_minmax(12rem,15rem)_auto_auto] xl:items-end" method="GET" action="{{ route('sessions.index') }}">
                    <div>
                        <label class="block text-sm font-semibold" for="sessions-branch">{{ __('sessions.branch_select_label') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="sessions-branch" name="branch_id">
                            <option value="">{{ __('sessions.all_branches') }}</option>
                            @foreach ($branchList as $branch)
                                <option value="{{ $branch->id }}" @selected($filterBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="sessions-family-search">{{ __('sessions.family_search_label') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="sessions-family-search" name="q" type="search" value="{{ $filterQuery }}" placeholder="{{ __('sessions.family_search_placeholder') }}" autocomplete="off" maxlength="100" dir="auto">
                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('sessions.family_search_hint') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="sessions-status">{{ __('sessions.status_filter_label') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="sessions-status" name="status">
                            <option value="">{{ __('sessions.all_statuses') }}</option>
                            @foreach (__('sessions.statuses') as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected($filterStatus === $statusValue)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('sessions.apply_filter') }}</button>
                    @if ($paginationQuery !== [])
                        <a class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('sessions.index') }}">{{ __('sessions.clear_filters') }}</a>
                    @endif
                </form>
            @endif
            @if ($branchList->isNotEmpty())
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="{{ __('sessions.occupancy') }}">
                    @foreach ($branchList as $branch)
                        @php
                            $branchOccupancy = $occupancyFor($branch->id);
                            $branchUsed = (int) data_get($branchOccupancy, 'active_count', 0);
                            $branchCapacity = data_get($branchOccupancy, 'capacity');
                            $branchFull = $branchCapacity !== null && $branchUsed >= (int) $branchCapacity;
                        @endphp
                        <div class="flex items-center justify-between gap-3 rounded-[10px] border border-[var(--pn-border)] px-3 py-2.5 {{ $branchFull ? 'bg-[var(--pn-warning-soft)]' : 'bg-[var(--pn-surface-subtle)]' }}">
                            <span class="min-w-0 truncate text-sm font-semibold">{{ $branch->name }}</span>
                            <span class="shrink-0 text-sm tabular-nums {{ $branchFull ? 'font-bold text-[var(--pn-warning)]' : 'text-[var(--pn-ink-muted)]' }}"><bdi dir="ltr">{{ $branchCapacity === null ? '—' : $branchUsed.'/'.$branchCapacity }}</bdi></span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($canCheckIn)
            <section class="{{ $managerBoardFirst ? 'order-3' : 'order-1' }} mt-6 rounded-[14px] border-2 border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] p-5 sm:p-6" data-pn-session-scan-station aria-labelledby="check-in-heading">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold" id="check-in-heading">{{ __('sessions.check_in_heading') }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.check_in_description') }}</p>
                    </div>
                    <span class="grid size-11 shrink-0 place-items-center rounded-[10px] border border-[var(--pn-primary)] bg-[var(--pn-surface)] text-[var(--pn-primary)]" aria-hidden="true">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M7 12h10M12 7v10"/></svg>
                    </span>
                </div>
                <p class="mt-4 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-3 text-sm font-semibold text-[var(--pn-ink)]" id="check-in-consequence">{{ __('sessions.check_in_consequence') }}</p>
                @if ($selectedCapacity !== null && $selectedUsed >= (int) $selectedCapacity)
                    <p class="mt-3 text-sm font-semibold text-[var(--pn-warning)]" role="status">{{ __('sessions.check_in_capacity_warning') }}</p>
                @endif
                <form class="mt-5 grid gap-4 lg:grid-cols-[minmax(12rem,18rem)_minmax(0,1fr)_auto] lg:items-end" method="POST" action="{{ route('sessions.check-in') }}" data-pn-form data-pn-submit data-pn-session-check-in aria-describedby="check-in-consequence">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $checkInKey }}">
                    <div>
                        <label class="block text-sm font-semibold" for="check-in-branch">{{ __('sessions.check_in_branch_label') }}</label>
                        <select class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="check-in-branch" name="branch_id" required autocomplete="off">
                            @foreach ($branchList as $branch)
                                @if (in_array((int) $branch->id, $checkInBranchIds, true))
                                    <option value="{{ $branch->id }}" @selected($checkInBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-1 text-sm text-[var(--pn-danger)]" id="check-in-branch-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="check-in-code">{{ __('sessions.check_in_code_label') }}</label>
                        <input class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 font-mono font-semibold tracking-wide focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="check-in-code" name="code" type="text" value="" placeholder="{{ __('sessions.check_in_code_placeholder') }}" autocomplete="off" autocapitalize="characters" spellcheck="false" inputmode="text" maxlength="512" required @if ($checkInResult === '' && ! $errors->any()) autofocus @endif enterkeyhint="done" aria-describedby="check-in-code-hint">
                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="check-in-code-hint">{{ __('sessions.check_in_code_hint') }}</p>
                        @error('code')<p class="mt-1 text-sm text-[var(--pn-danger)]" id="check-in-code-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit" data-pn-submit data-pn-loading-label="{{ __('sessions.check_in_loading') }}">{{ __('sessions.check_in_submit') }}</button>
                </form>
            </section>
        @else
            <section class="order-3 mt-6 rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface-subtle)] p-5 sm:p-6" data-pn-session-read-only aria-labelledby="sessions-read-only-heading" role="status">
                <h2 class="text-lg font-bold" id="sessions-read-only-heading">{{ __('sessions.read_only_heading') }}</h2>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.read_only_description') }}</p>
            </section>
        @endif

        <section class="{{ $managerBoardFirst ? 'order-2' : 'order-3' }} mt-8" data-pn-session-board aria-labelledby="live-board-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold" id="live-board-heading">{{ __('sessions.board_heading') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.board_description') }}</p>
                </div>
                @if (is_object($sessionList) && method_exists($sessionList, 'total'))
                    <span class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.board_count', ['count' => $sessionList->total()]) }}</span>
                @endif
            </div>

            @if ($sessionList->isEmpty())
                <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                    <p class="font-semibold">{{ $paginationQuery === [] ? __('sessions.empty_all_heading') : __('sessions.empty_heading') }}</p>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ $paginationQuery === [] ? __('sessions.empty_all_description') : __('sessions.empty_description') }}</p>
                </div>
            @else
                <ul class="mt-4 grid list-none gap-4 p-0 lg:grid-cols-2" aria-label="{{ __('sessions.board_heading') }}">
                    @foreach ($sessionList as $session)
                        @php
                            $sessionStatus = strtolower((string) (data_get($session, 'status') ?? data_get($session, 'state', 'active')));
                            $sessionStatusLabel = data_get(__('sessions.statuses'), $sessionStatus, $sessionStatus ?: __('sessions.statuses.active'));
                            $sessionStatusClass = $statusClasses[$sessionStatus] ?? 'border-[var(--pn-border-strong)] bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]';
                            $childName = data_get($session, 'child.full_name') ?? data_get($session, 'child_name') ?? __('sessions.unnamed_child');
                            $guardianName = data_get($session, 'guardian.full_name') ?? data_get($session, 'guardian_name') ?? __('sessions.unnamed_guardian');
                            $branchName = data_get($session, 'branch.name') ?? __('sessions.unknown_branch');
                            $ticketCode = data_get($session, 'ticket.display_code') ?? data_get($session, 'ticket.code') ?? data_get($session, 'ticket_code') ?? __('sessions.unknown_ticket');
                            $sessionTimezone = (string) (data_get($session, 'pricing_snapshot_json.branch_timezone') ?? data_get($session, 'ticket.price_snapshot_json.branch_timezone') ?? data_get($session, 'branch_timezone') ?? data_get($session, 'branch.timezone') ?? config('app.timezone'));
                            try {
                                $sessionTimezoneObject = new \DateTimeZone($sessionTimezone);
                            } catch (\Throwable) {
                                $sessionTimezoneObject = new \DateTimeZone('UTC');
                            }
                            $startedAt = data_get($session, 'started_at') ?? data_get($session, 'start_at');
                            $expectedEndAt = data_get($session, 'expected_end_at') ?? data_get($session, 'expected_ends_at') ?? data_get($session, 'ends_at');
                            $estimate = data_get($session, 'live_estimate');
                            $startedLabel = __('sessions.not_available');
                            $expectedEndLabel = __('sessions.not_available');
                            $elapsedLabel = __('sessions.not_available');
                            $estimateAsOfLabel = $serverClock->setTimezone($sessionTimezoneObject)->format('Y-m-d H:i:s');
                            if ($startedAt instanceof \DateTimeInterface) {
                                $startedLabel = $startedAt->setTimezone($sessionTimezoneObject)->format('Y-m-d H:i');
                                if (in_array($sessionStatus, ['active', 'paused'], true)) {
                                    $elapsedSeconds = max(0, $serverClock->getTimestamp() - $startedAt->getTimestamp());
                                    $elapsedLabel = __('sessions.elapsed_value', ['minutes' => intdiv($elapsedSeconds, 60), 'seconds' => str_pad((string) ($elapsedSeconds % 60), 2, '0', STR_PAD_LEFT)]);
                                } else {
                                    $elapsedLabel = __('sessions.elapsed_stopped');
                                }
                            }
                            if ($expectedEndAt instanceof \DateTimeInterface) {
                                $expectedEndLabel = $expectedEndAt->setTimezone($sessionTimezoneObject)->format('Y-m-d H:i');
                            }
                            $eligibleGuardians = collect(data_get($session, 'child.guardians', []));
                            $canCheckout = (bool) data_get($session, 'can_checkout', false);
                            $canOverrideCheckout = (bool) data_get($session, 'can_override_checkout', false);
                            $checkoutSessionId = (string) data_get($session, 'id');
                            $showCheckoutErrors = $checkoutErrorSessionId !== '' && $checkoutErrorSessionId === $checkoutSessionId;
                            $checkoutIdempotencyKey = $showCheckoutErrors && filled(old('idempotency_key'))
                                ? (string) old('idempotency_key')
                                : (string) \Illuminate\Support\Str::uuid();
                            $checkoutGuardianId = $showCheckoutErrors ? old('guardian_id') : null;
                            $checkoutLastFour = $showCheckoutErrors ? (string) old('phone_last_four', '') : '';
                            $checkoutVerificationMethod = $showCheckoutErrors ? (string) old('verification_method', 'phone_last_four') : 'phone_last_four';
                            $checkoutOverrideReason = $showCheckoutErrors ? (string) old('override_reason', '') : '';
                            $checkoutSnapshot = data_get($session, 'checkout_snapshot_json');
                            $pendingAmountMinor = data_get($session, 'checkout_amount_due_minor', data_get($checkoutSnapshot, 'total_minor'));
                            $pendingCurrency = (string) data_get($checkoutSnapshot, 'currency', '');
                            $pendingPreparedAt = data_get($session, 'checkout_prepared_at');
                            $pendingPreparedLabel = $pendingPreparedAt instanceof \DateTimeInterface
                                ? $pendingPreparedAt->setTimezone($sessionTimezoneObject)->format('Y-m-d H:i')
                                : __('sessions.not_available');
                            $pendingAmountLabel = $pendingAmountMinor === null || $pendingCurrency === ''
                                ? __('sessions.checkout.amount_unavailable')
                                : $formatMoney($pendingAmountMinor, $pendingCurrency);
                            $dueState = 'stopped';
                            $dueStateClass = 'border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]';
                            if (in_array($sessionStatus, ['active', 'paused'], true) && $expectedEndAt instanceof \DateTimeInterface) {
                                $dueState = $serverClock->greaterThan($expectedEndAt) ? 'overdue' : ($serverClock->greaterThanOrEqualTo($expectedEndAt) ? 'due' : 'on_time');
                                $dueStateClass = match ($dueState) {
                                    'overdue' => 'border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] text-[var(--pn-danger)]',
                                    'due' => 'border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] text-[var(--pn-warning)]',
                                    default => 'border-[var(--pn-success)] bg-[var(--pn-success-soft)] text-[var(--pn-success)]',
                                };
                            }
                            $pendingSnapshot = is_array($checkoutSnapshot) ? $checkoutSnapshot : [];
                            $pendingSubtotalLabel = data_get($pendingSnapshot, 'subtotal_minor') === null || $pendingCurrency === ''
                                ? __('sessions.checkout.amount_unavailable')
                                : $formatMoney(data_get($pendingSnapshot, 'subtotal_minor'), $pendingCurrency);
                            $pendingTaxLabel = data_get($pendingSnapshot, 'tax_minor') === null || $pendingCurrency === ''
                                ? __('sessions.checkout.amount_unavailable')
                                : $formatMoney(data_get($pendingSnapshot, 'tax_minor'), $pendingCurrency);
                            $pendingTotalLabel = data_get($pendingSnapshot, 'total_minor') === null || $pendingCurrency === ''
                                ? $pendingAmountLabel
                                : $formatMoney(data_get($pendingSnapshot, 'total_minor'), $pendingCurrency);
                            $canExtend = $sessionStatus === 'active' && $canCheckout;
                            $canManageSession = $sessionStatus === 'active' && $canOverrideCheckout;
                            $sessionExtendKey = (string) \Illuminate\Support\Str::uuid();
                            $sessionAdjustmentKey = (string) \Illuminate\Support\Str::uuid();
                            $sessionCancelKey = (string) \Illuminate\Support\Str::uuid();
                        @endphp
                        <li class="flex min-w-0 flex-col rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm" data-pn-session-card>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-[var(--pn-primary)]">{{ $branchName }}</p>
                                    <h3 class="mt-1 truncate text-lg font-bold">{{ $childName }}</h3>
                                    <p class="mt-1 truncate text-sm text-[var(--pn-ink-muted)]">{{ __('sessions.guardian') }}: {{ $guardianName }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <span class="inline-flex min-h-8 items-center rounded-full border px-3 text-sm font-semibold {{ $sessionStatusClass }}">{{ $sessionStatusLabel }}</span>
                                    @if ($dueState !== 'stopped')
                                        <span class="inline-flex min-h-7 items-center rounded-full border px-2.5 text-xs font-semibold {{ $dueStateClass }}" data-pn-due-indicator data-due-state="{{ $dueState }}" aria-label="{{ __('sessions.due_states.'.$dueState) }}">
                                            {{ __('sessions.due_states.'.$dueState) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <dl class="mt-5 grid gap-3 border-t border-[var(--pn-border)] pt-4 text-sm sm:grid-cols-2">
                                <div class="min-w-0">
                                    <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.ticket') }}</dt>
                                    <dd class="mt-1 truncate font-semibold"><bdi dir="ltr">{{ $ticketCode }}</bdi></dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.started_at') }}</dt>
                                    <dd class="mt-1 tabular-nums"><bdi dir="ltr">{{ $startedLabel }}</bdi></dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.elapsed') }}</dt>
                                    <dd class="mt-1 tabular-nums"><bdi dir="ltr">{{ $elapsedLabel }}</bdi></dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.expected_end') }}</dt>
                                    <dd class="mt-1 tabular-nums"><bdi dir="ltr">{{ $expectedEndLabel }}</bdi></dd>
                                </div>
                            </dl>
                            @if (is_array($estimate))
                                <section class="mt-5 rounded-[12px] border border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] p-4" data-pn-live-estimate aria-label="{{ __('sessions.estimate.heading') }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h4 class="font-bold text-[var(--pn-primary)]">{{ __('sessions.estimate.heading') }}</h4>
                                            <p class="mt-1 text-xs tabular-nums text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ __('sessions.estimate.as_of', ['time' => $estimateAsOfLabel]) }}</bdi></p>
                                        </div>
                                        <div class="text-start">
                                            <p class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.total') }}</p>
                                            <p class="mt-1 text-xl font-bold tabular-nums text-[var(--pn-ink)]"><bdi dir="ltr">{{ $formatMoney(data_get($estimate, 'total_minor'), data_get($estimate, 'currency')) }}</bdi></p>
                                        </div>
                                    </div>
                                    <dl class="mt-4 grid gap-x-4 gap-y-3 border-t border-[var(--pn-border)] pt-4 text-sm sm:grid-cols-2">
                                        <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.base') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ $formatMoney(data_get($estimate, 'base_price_minor'), data_get($estimate, 'currency')) }}</bdi></dd></div>
                                        <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.grace_included') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ __('sessions.elapsed_value', ['minutes' => intdiv((int) data_get($session, 'pricing_snapshot_json.grace_period_seconds', 0), 60), 'seconds' => str_pad((string) ((int) data_get($session, 'pricing_snapshot_json.grace_period_seconds', 0) % 60), 2, '0', STR_PAD_LEFT)]) }}</bdi></dd></div>
                                        <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.overtime_duration') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ __('sessions.elapsed_value', ['minutes' => intdiv((int) data_get($estimate, 'overtime_seconds'), 60), 'seconds' => str_pad((string) ((int) data_get($estimate, 'overtime_seconds') % 60), 2, '0', STR_PAD_LEFT)]) }}</bdi></dd></div>
                                        <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.overtime_units') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ data_get($estimate, 'overtime_units') }}</bdi></dd></div>
                                        <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.subtotal_before_tax') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ $formatMoney(data_get($estimate, 'net_minor'), data_get($estimate, 'currency')) }}</bdi></dd></div>
                                        <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.tax_rate') }}</dt><dd class="mt-1 font-semibold"><bdi dir="ltr">{{ $formatTaxRate(data_get($estimate, 'tax_rate_bps')) }}</bdi> · {{ __('sessions.estimate.tax_mode.'.data_get($estimate, 'tax_mode')) }}</dd></div>
                                        <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.tax_amount') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ $formatMoney(data_get($estimate, 'tax_minor'), data_get($estimate, 'currency')) }}</bdi></dd></div>
                                    </dl>
                                    <p class="mt-4 border-t border-[var(--pn-border)] pt-3 text-xs font-semibold leading-5 text-[var(--pn-ink-muted)]">{{ __('sessions.estimate.not_final_disclaimer') }}</p>
                                </section>
                            @endif
                            @if ($sessionStatus === 'active' && ($canExtend || $canManageSession))
                                <section class="mt-5 border-t border-[var(--pn-border)] pt-5" data-pn-session-actions aria-labelledby="session-actions-{{ $checkoutSessionId }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h4 class="font-bold" id="session-actions-{{ $checkoutSessionId }}">{{ __('sessions.actions.heading') }}</h4>
                                            <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.actions.description') }}</p>
                                        </div>
                                        <span class="inline-flex min-h-7 items-center rounded-full border border-[var(--pn-border-strong)] bg-[var(--pn-surface-subtle)] px-2.5 text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.actions.no_pause') }}</span>
                                    </div>
                                    <div class="mt-4 grid gap-4 xl:grid-cols-2">
                                        @if ($canExtend)
                                            <form class="rounded-[12px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4" method="POST" action="{{ route('sessions.extend', ['session' => $checkoutSessionId]) }}" data-pn-form data-pn-session-extend aria-describedby="session-extend-consequence-{{ $checkoutSessionId }}">
                                                @csrf
                                                <input type="hidden" name="expected_lock_version" value="{{ data_get($session, 'lock_version') }}">
                                                <input type="hidden" name="idempotency_key" value="{{ $sessionExtendKey }}">
                                                <h5 class="font-bold">{{ __('sessions.actions.extend_heading') }}</h5>
                                                <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]" id="session-extend-consequence-{{ $checkoutSessionId }}">{{ __('sessions.actions.extend_description') }}</p>
                                                <div class="mt-4 flex flex-wrap items-end gap-3">
                                                    <div class="min-w-44 flex-1">
                                                        <label class="block text-sm font-semibold" for="session-extend-units-{{ $checkoutSessionId }}">{{ __('sessions.actions.extension_label') }}</label>
                                                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="session-extend-units-{{ $checkoutSessionId }}" name="extension_units" required>
                                                            <option value="1">{{ __('sessions.actions.extension_option', ['minutes' => 30]) }}</option>
                                                            <option value="2">{{ __('sessions.actions.extension_option', ['minutes' => 60]) }}</option>
                                                            <option value="3">{{ __('sessions.actions.extension_option', ['minutes' => 90]) }}</option>
                                                        </select>
                                                    </div>
                                                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit" data-pn-submit data-pn-loading-label="{{ __('sessions.actions.extend_loading') }}">{{ __('sessions.actions.extend_submit') }}</button>
                                                </div>
                                            </form>
                                        @endif
                                        @if ($canManageSession)
                                            <details class="rounded-[12px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4" data-pn-session-adjustment>
                                                <summary class="cursor-pointer font-bold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('sessions.actions.adjust_heading') }}</summary>
                                                <p class="mt-2 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.actions.adjust_description') }}</p>
                                                <form class="mt-4 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('sessions.adjustments.store', ['session' => $checkoutSessionId]) }}" data-pn-form aria-describedby="session-adjustment-reason-hint-{{ $checkoutSessionId }}">
                                                    @csrf
                                                    <input type="hidden" name="expected_lock_version" value="{{ data_get($session, 'lock_version') }}">
                                                    <input type="hidden" name="idempotency_key" value="{{ $sessionAdjustmentKey }}">
                                                    <div>
                                                        <label class="block text-sm font-semibold" for="session-adjust-extension-{{ $checkoutSessionId }}">{{ __('sessions.actions.extension_units_label') }}</label>
                                                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="session-adjust-extension-{{ $checkoutSessionId }}" name="extension_units" type="number" min="0" max="48" value="0" inputmode="numeric">
                                                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('sessions.actions.extension_units_hint') }}</p>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-semibold" for="session-adjust-amount-{{ $checkoutSessionId }}">{{ __('sessions.actions.adjustment_minor_label') }}</label>
                                                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="session-adjust-amount-{{ $checkoutSessionId }}" name="adjustment_minor" type="number" min="-99999999900" max="99999999900" value="0" inputmode="numeric">
                                                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('sessions.actions.adjustment_minor_hint') }}</p>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="block text-sm font-semibold" for="session-adjust-reason-{{ $checkoutSessionId }}">{{ __('sessions.actions.reason_label') }}</label>
                                                        <textarea class="mt-2 min-h-24 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="session-adjust-reason-{{ $checkoutSessionId }}" name="reason" minlength="1" maxlength="500" required aria-describedby="session-adjustment-reason-hint-{{ $checkoutSessionId }}"></textarea>
                                                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="session-adjustment-reason-hint-{{ $checkoutSessionId }}">{{ __('sessions.actions.reason_hint') }}</p>
                                                    </div>
                                                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-primary)] hover:bg-[var(--pn-primary-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2 sm:col-span-2 sm:justify-self-start" type="submit" data-pn-submit data-pn-loading-label="{{ __('sessions.actions.adjust_loading') }}">{{ __('sessions.actions.adjust_submit') }}</button>
                                                </form>
                                            </details>
                                            <details class="rounded-[12px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4" data-pn-session-cancel>
                                                <summary class="cursor-pointer font-bold text-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('sessions.actions.cancel_heading') }}</summary>
                                                <p class="mt-2 text-sm leading-6 text-[var(--pn-ink)]">{{ __('sessions.actions.cancel_consequence') }}</p>
                                                <form class="mt-4" method="POST" action="{{ route('sessions.cancel', ['session' => $checkoutSessionId]) }}" data-pn-form data-confirm="{{ __('sessions.actions.cancel_confirm', ['child' => $childName]) }}" aria-describedby="session-cancel-reason-hint-{{ $checkoutSessionId }}">
                                                    @csrf
                                                    <input type="hidden" name="expected_lock_version" value="{{ data_get($session, 'lock_version') }}">
                                                    <input type="hidden" name="idempotency_key" value="{{ $sessionCancelKey }}">
                                                    <label class="block text-sm font-semibold" for="session-cancel-reason-{{ $checkoutSessionId }}">{{ __('sessions.actions.reason_label') }}</label>
                                                    <textarea class="mt-2 min-h-24 w-full rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] px-3 py-2 focus:border-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="session-cancel-reason-{{ $checkoutSessionId }}" name="reason" minlength="1" maxlength="500" required aria-describedby="session-cancel-reason-hint-{{ $checkoutSessionId }}"></textarea>
                                                    <p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="session-cancel-reason-hint-{{ $checkoutSessionId }}">{{ __('sessions.actions.cancel_reason_hint') }}</p>
                                                    <button class="mt-3 inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-danger)] px-4 font-semibold text-[var(--pn-danger)] hover:bg-[var(--pn-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit" data-pn-submit data-pn-loading-label="{{ __('sessions.actions.cancel_loading') }}">{{ __('sessions.actions.cancel_submit') }}</button>
                                                </form>
                                            </details>
                                        @endif
                                    </div>
                                </section>
                            @endif
                            @if ($sessionStatus === 'pending_payment')
                                <section class="mt-5 rounded-[12px] border-2 border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-4" data-pn-checkout-state aria-labelledby="checkout-pending-{{ $checkoutSessionId }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-[var(--pn-warning)]" id="checkout-pending-{{ $checkoutSessionId }}">{{ __('sessions.checkout.pending_heading') }}</h4>
                                            <p class="mt-1 text-sm leading-6 text-[var(--pn-ink)]">{{ __('sessions.checkout.pending_description') }}</p>
                                        </div>
                                        <div class="shrink-0 text-start">
                                            <p class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.pending_amount') }}</p>
                                            <p class="mt-1 text-xl font-bold tabular-nums text-[var(--pn-ink)]"><bdi dir="ltr">{{ $pendingAmountLabel }}</bdi></p>
                                        </div>
                                    </div>
                                    <dl class="mt-4 grid gap-3 border-t border-[var(--pn-border)] pt-4 text-sm sm:grid-cols-2">
                                        <div>
                                            <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.pending_prepared_at') }}</dt>
                                            <dd class="mt-1 tabular-nums"><bdi dir="ltr">{{ $pendingPreparedLabel }}</bdi></dd>
                                        </div>
                                        @if ($checkoutGuardian = $eligibleGuardians->firstWhere('id', (int) data_get($session, 'checkout_guardian_id')))
                                            <div>
                                                <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.verified_guardian') }}</dt>
                                                <dd class="mt-1 truncate font-semibold">{{ $checkoutGuardian->full_name }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                    <div class="mt-4 border-t border-[var(--pn-border)] pt-4" data-pn-frozen-invoice>
                                        <h5 class="font-bold">{{ __('sessions.checkout.frozen_invoice_heading') }}</h5>
                                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                                            <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.subtotal') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ $pendingSubtotalLabel }}</bdi></dd></div>
                                            <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.tax') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ $pendingTaxLabel }}</bdi></dd></div>
                                            <div><dt class="text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.total') }}</dt><dd class="mt-1 font-semibold tabular-nums"><bdi dir="ltr">{{ $pendingTotalLabel }}</bdi></dd></div>
                                        </dl>
                                        <p class="mt-3 text-xs font-semibold leading-5 text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.frozen_invoice_hint') }}</p>
                                    </div>
                                    <p class="mt-4 border-t border-[var(--pn-border)] pt-3 text-sm font-semibold leading-6 text-[var(--pn-ink)]">{{ __('sessions.checkout.pending_handoff') }}</p>
                                </section>
                            @elseif ($sessionStatus === 'active' && $canCheckout)
                                <section class="mt-5 rounded-[12px] border border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] p-4" data-pn-checkout aria-labelledby="checkout-heading-{{ $checkoutSessionId }}">
                                    <div>
                                        <h4 class="font-bold text-[var(--pn-primary)]" id="checkout-heading-{{ $checkoutSessionId }}">{{ __('sessions.checkout.heading') }}</h4>
                                        <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('sessions.checkout.description') }}</p>
                                    </div>
                                    <p class="mt-3 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-3 text-sm font-semibold leading-6 text-[var(--pn-ink)]" id="checkout-consequence-{{ $checkoutSessionId }}">{{ __('sessions.checkout.consequence') }}</p>
                                    @if ($eligibleGuardians->isEmpty())
                                        <p class="mt-4 rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-3 text-sm font-semibold text-[var(--pn-ink-muted)]" role="status">{{ __('sessions.checkout.no_guardians') }}</p>
                                    @else
                                        <form class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(9rem,12rem)_minmax(11rem,16rem)_auto] lg:items-end" method="POST" action="{{ route('sessions.checkout.prepare', ['session' => $checkoutSessionId]) }}" data-pn-form data-pn-submit data-pn-checkout-form aria-describedby="checkout-consequence-{{ $checkoutSessionId }}">
                                            @csrf
                                            <input type="hidden" name="checkout_session_id" value="{{ $checkoutSessionId }}">
                                            <input type="hidden" name="expected_lock_version" value="{{ data_get($session, 'lock_version') }}">
                                            @if ($canOverrideCheckout)
                                                <div>
                                                    <label class="block text-sm font-semibold" for="checkout-method-{{ $checkoutSessionId }}">{{ __('sessions.checkout.verification_method_label') }}</label>
                                                    <select class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="checkout-method-{{ $checkoutSessionId }}" name="verification_method" required @if ($showCheckoutErrors && $errors->has('verification_method')) aria-invalid="true" @endif aria-describedby="checkout-method-hint-{{ $checkoutSessionId }}">
                                                        <option value="phone_last_four" @selected($checkoutVerificationMethod === 'phone_last_four')>{{ __('sessions.checkout.verification_method_phone_last_four') }}</option>
                                                        <option value="manager_override" @selected($checkoutVerificationMethod === 'manager_override')>{{ __('sessions.checkout.verification_method_manager_override') }}</option>
                                                    </select>
                                                    <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]" id="checkout-method-hint-{{ $checkoutSessionId }}">{{ __('sessions.checkout.override_warning') }}</p>
                                                    @if ($showCheckoutErrors && $errors->has('verification_method'))
                                                        <p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $errors->first('verification_method') }}</p>
                                                    @endif
                                                    <label class="mt-3 block text-sm font-semibold" for="checkout-override-reason-{{ $checkoutSessionId }}">{{ __('sessions.checkout.override_reason_label') }}</label>
                                                    <input class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="checkout-override-reason-{{ $checkoutSessionId }}" name="override_reason" type="text" value="{{ $checkoutOverrideReason }}" placeholder="{{ __('sessions.checkout.override_reason_placeholder') }}" minlength="10" maxlength="500" autocomplete="off" @if ($showCheckoutErrors && $errors->has('override_reason')) aria-invalid="true" @endif aria-describedby="checkout-override-reason-hint-{{ $checkoutSessionId }}">
                                                    <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]" id="checkout-override-reason-hint-{{ $checkoutSessionId }}">{{ __('sessions.checkout.override_reason_hint') }}</p>
                                                    @if ($showCheckoutErrors && $errors->has('override_reason'))
                                                        <p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $errors->first('override_reason') }}</p>
                                                    @endif
                                                </div>
                                            @else
                                                <input type="hidden" name="verification_method" value="phone_last_four">
                                            @endif
                                            <input type="hidden" name="idempotency_key" value="{{ $checkoutIdempotencyKey }}">
                                            <div>
                                                <label class="block text-sm font-semibold" for="checkout-guardian-{{ $checkoutSessionId }}">{{ __('sessions.checkout.guardian_label') }}</label>
                                                <select class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="checkout-guardian-{{ $checkoutSessionId }}" name="guardian_id" @required(! $canOverrideCheckout) @if ($showCheckoutErrors && $errors->has('guardian_id')) aria-invalid="true" @endif aria-describedby="checkout-guardian-hint-{{ $checkoutSessionId }}">
                                                    <option value="">{{ __('sessions.checkout.guardian_placeholder') }}</option>
                                                    @foreach ($eligibleGuardians as $eligibleGuardian)
                                                        <option value="{{ $eligibleGuardian->id }}" @selected((string) $checkoutGuardianId === (string) $eligibleGuardian->id)>{{ $eligibleGuardian->full_name }}</option>
                                                    @endforeach
                                                </select>
                                                <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]" id="checkout-guardian-hint-{{ $checkoutSessionId }}">{{ __('sessions.checkout.guardian_hint') }}</p>
                                                @if ($showCheckoutErrors && $errors->has('guardian_id'))
                                                    <p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $errors->first('guardian_id') }}</p>
                                                @endif
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold" for="checkout-phone-last-four-{{ $checkoutSessionId }}">{{ __('sessions.checkout.phone_last_four_label') }}</label>
                                                <input class="mt-2 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 font-mono font-semibold tracking-[0.2em] focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="checkout-phone-last-four-{{ $checkoutSessionId }}" name="phone_last_four" type="text" value="{{ $checkoutLastFour }}" placeholder="{{ __('sessions.checkout.phone_last_four_placeholder') }}" inputmode="numeric" autocomplete="off" maxlength="4" pattern="[0-9]{4}" @required(! $canOverrideCheckout) @if ($showCheckoutErrors && $errors->has('phone_last_four')) aria-invalid="true" @endif aria-describedby="checkout-phone-last-four-hint-{{ $checkoutSessionId }}">
                                                <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]" id="checkout-phone-last-four-hint-{{ $checkoutSessionId }}">{{ __('sessions.checkout.phone_last_four_hint') }}</p>
                                                @if ($showCheckoutErrors && $errors->has('phone_last_four'))
                                                    <p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $errors->first('phone_last_four') }}</p>
                                                @endif
                                            </div>
                                            <button class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit" data-pn-submit data-pn-loading-label="{{ __('sessions.checkout.loading') }}">{{ __('sessions.checkout.submit') }}</button>
                                        </form>
                                    @endif
                                </section>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (is_object($sessionList) && method_exists($sessionList, 'hasPages') && $sessionList->hasPages())
                <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('sessions.board_heading') }}">
                    <div class="flex flex-wrap gap-2">
                        @if ($sessionList->previousPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $sessionList->previousPageUrl() }}">{{ __('sessions.previous_page') }}</a>
                        @endif
                        @if ($sessionList->nextPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $sessionList->nextPageUrl() }}">{{ __('sessions.next_page') }}</a>
                        @endif
                    </div>
                    <span class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('sessions.pagination_status', ['current' => $sessionList->currentPage(), 'last' => $sessionList->lastPage()]) }}</span>
                </nav>
            @endif
        </section>
        </div>
    </main>
@endsection
