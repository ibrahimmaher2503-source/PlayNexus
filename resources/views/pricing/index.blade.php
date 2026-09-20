@extends('layouts.app')

@section('title', __('pricing.page_title').' · PlayNexus')

@section('content')
    @php
        $branchList = collect($branches ?? []);
        $manageableBranchList = collect($manageableBranches ?? []);
        $ruleList = collect($rules ?? []);
        $ticketTypeList = collect($ticketTypes ?? []);
        $selectedBranchId = (int) old('branch_id', request()->query('branch_id', session('branch_id', 0)));
        $selectedBranch = $branchList->firstWhere('id', $selectedBranchId);
        $manageableBranchIds = $manageableBranchList->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $oldFormContext = old('form_context');
        if ($manageableBranchIds === [] && ($canManage ?? false)) {
            $manageableBranchIds = $branchList->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        }
        $replacementRouteAvailable = app('router')->has('pricing.versions.store');
        $showReplacementControls = ($canManage ?? false) && $replacementRouteAvailable && $manageableBranchIds !== [];
        $activeTab = request()->query('tab') === 'types' ? 'types' : 'rules';
        $typeFormContext = 'ticket-type-create';
        $typeFormOpen = $oldFormContext === $typeFormContext && $errors->any();
        $panelAttributes = static fn (string $tab): string => $activeTab === $tab ? '' : 'hidden aria-hidden="true"';
        $typeBranchId = (string) old('branch_id', $selectedBranchId ?: ($manageableBranchList->first()?->id ?? ''));
        if ($selectedBranchId > 0) {
            $ruleList = $ruleList
                ->filter(fn (mixed $rule): bool => (int) data_get($rule, 'branch_id') === $selectedBranchId)
                ->values();
        }
        $formatEgp = static function (mixed $minor): string {
            $value = max(0, (int) $minor);
            return number_format(intdiv($value, 100), 0, '.', ',').'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
        };
        $formatEgpInput = static function (mixed $minor): string {
            $value = max(0, (int) $minor);
            return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
        };
        $formatPercent = static function (mixed $basisPoints): string {
            $value = max(0, (int) $basisPoints);
            return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT).'%';
        };
    @endphp

    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6" data-pn-pricing>
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('pricing.page_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('pricing.page_description') }}</p>
        </header>

        <nav class="mt-5 overflow-x-auto border-b border-[var(--pn-border)]" aria-label="{{ __('pricing.tabs_label') }}" data-pn-pricing-tabs>
            <div class="flex min-w-max gap-1" role="tablist">
                @foreach (['rules' => __('pricing.tabs.rules'), 'types' => __('pricing.tabs.types')] as $tab => $label)
                    <a class="inline-flex min-h-11 items-center border-b-2 px-4 py-2 text-sm font-semibold {{ $activeTab === $tab ? 'border-[var(--pn-primary)] text-[var(--pn-primary)]' : 'border-transparent text-[var(--pn-ink-muted)] hover:border-[var(--pn-border-strong)] hover:text-[var(--pn-ink)]' }} focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-inset" href="{{ route('pricing.index', array_filter(['tab' => $tab, 'branch_id' => $selectedBranchId ?: null])) }}" role="tab" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}" aria-controls="pricing-tab-{{ $tab }}" @if ($activeTab === $tab) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </nav>

        @if (session('success') || session('status_message'))
            <p class="mt-6 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') ?? session('status_message') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="pricing-errors" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('pricing.validation_failed') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('conflict') || $errors->has('expected_version'))
            <div class="mt-6 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-4 text-[var(--pn-ink)]" id="pricing-conflict" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('pricing.replacement_conflict_title') }}</p>
                <p class="mt-1 text-sm leading-6">{{ session('conflict') ?? $errors->first('expected_version') }}</p>
            </div>
        @endif

        <div id="pricing-tab-rules" data-pn-pricing-panel="rules" {!! $panelAttributes('rules') !!}>
        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="pricing-branch-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('pricing.branch_context') }}</p>
                    <h2 class="mt-1 text-lg font-bold" id="pricing-branch-heading">{{ $selectedBranch?->name ?? __('pricing.all_branches') }}</h2>
                </div>
                @if ($selectedBranch)
                    <span class="inline-flex min-h-9 items-center rounded-full border border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] px-3 text-sm font-semibold text-[var(--pn-primary)]"><bdi dir="ltr">{{ $selectedBranch->code ?? '—' }}</bdi></span>
                @endif
            </div>
            @if ($branchList->isNotEmpty())
                <form class="mt-5 flex flex-wrap items-end gap-3" method="GET" action="{{ route('pricing.index') }}">
                    <div class="min-w-64 flex-1">
                        <label class="block text-sm font-semibold" for="pricing-branch">{{ __('pricing.branch_select_label') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-branch" name="branch_id">
                            <option value="">{{ __('pricing.all_branches') }}</option>
                            @foreach ($branchList as $branch)
                                <option value="{{ $branch->id }}" @selected($selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('pricing.view_rules') }}</button>
                </form>
            @endif
        </section>

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-primary-soft)] p-5 sm:p-6" aria-labelledby="fixed-terms-heading">
            <h2 class="text-lg font-bold" id="fixed-terms-heading">{{ __('pricing.fixed_terms_heading') }}</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('pricing.grace_period') }}</dt>
                    <dd class="mt-1 font-bold"><bdi dir="ltr">{{ __('pricing.grace_period_value') }}</bdi></dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('pricing.overtime_rounding') }}</dt>
                    <dd class="mt-1 font-bold">{{ __('pricing.overtime_rounding_value') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('pricing.pause_policy') }}</dt>
                    <dd class="mt-1 font-bold">{{ __('pricing.pause_policy_value') }}</dd>
                </div>
            </dl>
        </section>

        <div class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(19rem,22rem)]">
            <section class="min-w-0" aria-labelledby="active-rules-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold" id="active-rules-heading">{{ __('pricing.active_rules_heading') }}</h2>
                        <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('pricing.active_rules_description') }}</p>
                    </div>
                    @if ($selectedBranch)
                        <span class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ $selectedBranch->name }}</span>
                    @endif
                </div>

                @if ($ruleList->isEmpty())
                    <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                        <p class="font-semibold">{{ __('pricing.no_rules') }}</p>
                        <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('pricing.empty_description') }}</p>
                    </div>
                @else
                    <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="active-rules-heading" tabindex="0">
                        <table class="min-w-full text-start">
                            <caption class="sr-only">{{ __('pricing.rules_table_caption') }}</caption>
                            <thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm">
                                <tr>
                                    <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.rule_name') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.branch') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.duration') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.base_price') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.overtime_price') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.tax_snapshot') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.status') }}</th>
                                    @if ($showReplacementControls)
                                        <th class="whitespace-nowrap px-4 py-3 text-start font-semibold" scope="col">{{ __('pricing.replacement_column') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--pn-border)]">
                                @foreach ($ruleList as $rule)
                                    @php
                                        $ruleBranch = $branchList->firstWhere('id', data_get($rule, 'branch_id'));
                                        $taxMode = data_get($rule, 'tax_mode');
                                    @endphp
                                    <tr class="align-top">
                                        <th class="px-4 py-4 text-start" scope="row">
                                            <span class="block font-semibold">{{ data_get($rule, 'name') }}</span>
                                            <span class="mt-1 block text-sm text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ data_get($rule, 'code') }}</bdi> · {{ __('pricing.version', ['version' => data_get($rule, 'version', 1)]) }}</span>
                                        </th>
                                        <td class="whitespace-nowrap px-4 py-4">{{ data_get($rule, 'branch.name') ?? $ruleBranch?->name ?? __('pricing.all_branches') }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 tabular-nums"><bdi dir="ltr">{{ intdiv((int) data_get($rule, 'base_duration_seconds', 0), 60) }} {{ __('pricing.minutes_suffix') }}</bdi></td>
                                        <td class="whitespace-nowrap px-4 py-4 tabular-nums"><bdi dir="ltr">{{ $formatEgp(data_get($rule, 'base_price_minor', 0)) }} EGP</bdi></td>
                                        <td class="whitespace-nowrap px-4 py-4 tabular-nums"><bdi dir="ltr">{{ $formatEgp(data_get($rule, 'overtime_price_minor', 0)) }} EGP</bdi></td>
                                        <td class="whitespace-nowrap px-4 py-4 tabular-nums"><bdi dir="ltr">{{ $formatPercent(data_get($rule, 'tax_rate_bps', 0)) }}</bdi><span class="block text-sm text-[var(--pn-ink-muted)]">{{ in_array($taxMode, ['inclusive', 'exclusive'], true) ? __('pricing.'.$taxMode) : '—' }}</span></td>
                                        <td class="whitespace-nowrap px-4 py-4"><span class="inline-flex min-h-8 items-center rounded-full border border-[var(--pn-success)] bg-[var(--pn-success-soft)] px-3 text-sm font-semibold text-[var(--pn-success)]">{{ __('pricing.active') }}</span></td>
                                        @if ($showReplacementControls)
                                            @php($canReplace = in_array((int) data_get($rule, 'branch_id'), $manageableBranchIds, true))
                                            @php($replacementFormContext = 'pricing-version-'.data_get($rule, 'id'))
                                            @php($isReplacementFormContext = $oldFormContext === $replacementFormContext)
                                            <td class="min-w-64 px-4 py-4 align-top">
                                                @if ($canReplace)
                                                    <details class="group rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)]" id="pricing-replacement-{{ data_get($rule, 'id') }}">
                                                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]">
                                                            <span>{{ __('pricing.replacement_summary') }}</span>
                                                            <span aria-hidden="true" class="text-lg leading-none transition-transform group-open:rotate-45">+</span>
                                                        </summary>
                                                        <div class="border-t border-[var(--pn-border)] p-3">
                                                            <p class="text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('pricing.replacement_description') }}</p>
                                                            <p class="mt-2 text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('pricing.replacement_history_notice') }}</p>
                                                            <dl class="mt-3 grid grid-cols-2 gap-2 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3 text-xs">
                                                                <div>
                                                                    <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('pricing.current_version') }}</dt>
                                                                    <dd class="mt-1 font-bold"><bdi dir="ltr">{{ __('pricing.version', ['version' => data_get($rule, 'version', 1)]) }}</bdi></dd>
                                                                </div>
                                                                <div>
                                                                    <dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('pricing.current_price') }}</dt>
                                                                    <dd class="mt-1 font-bold"><bdi dir="ltr">{{ $formatEgp(data_get($rule, 'base_price_minor', 0)) }} EGP</bdi></dd>
                                                                </div>
                                                            </dl>

                                                            <form class="mt-4 space-y-4" method="POST" action="{{ route('pricing.versions.store', $rule) }}" aria-describedby="replacement-help-{{ data_get($rule, 'id') }}">
                                                                @csrf
                                                                <input type="hidden" name="form_context" value="{{ $replacementFormContext }}">
                                                                <input type="hidden" name="expected_version" value="{{ data_get($rule, 'version', 1) }}">
                                                                <p class="sr-only" id="replacement-help-{{ data_get($rule, 'id') }}">{{ __('pricing.replacement_history_notice') }}</p>
                                                                <div>
                                                                    <label class="block text-sm font-semibold" for="replacement-name-{{ data_get($rule, 'id') }}">{{ __('pricing.replacement_name_label') }}</label>
                                                                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="replacement-name-{{ data_get($rule, 'id') }}" name="name" type="text" value="{{ $isReplacementFormContext ? old('name', data_get($rule, 'name')) : data_get($rule, 'name') }}" maxlength="190" required @if ($isReplacementFormContext && $errors->has('name')) aria-invalid="true" aria-describedby="replacement-name-error-{{ data_get($rule, 'id') }}" @endif>
                                                                    @if ($isReplacementFormContext)
                                                                        @error('name')
                                                                            <p class="mt-1 text-sm text-[var(--pn-danger)]" id="replacement-name-error-{{ data_get($rule, 'id') }}">{{ $message }}</p>
                                                                        @enderror
                                                                    @endif
                                                                </div>
                                                                <div>
                                                                    <label class="block text-sm font-semibold" for="replacement-duration-{{ data_get($rule, 'id') }}">{{ __('pricing.replacement_duration_label') }}</label>
                                                                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="replacement-duration-{{ data_get($rule, 'id') }}" name="base_duration_minutes" type="number" min="1" max="1440" step="1" value="{{ $isReplacementFormContext ? old('base_duration_minutes', intdiv((int) data_get($rule, 'base_duration_seconds', 0), 60)) : intdiv((int) data_get($rule, 'base_duration_seconds', 0), 60) }}" required>
                                                                    @if ($isReplacementFormContext)
                                                                        @error('base_duration_minutes')
                                                                            <p class="mt-1 text-sm text-[var(--pn-danger)]" id="replacement-duration-error-{{ data_get($rule, 'id') }}">{{ $message }}</p>
                                                                        @enderror
                                                                    @endif
                                                                </div>
                                                                <div>
                                                                    <label class="block text-sm font-semibold" for="replacement-base-price-{{ data_get($rule, 'id') }}">{{ __('pricing.replacement_base_price_label') }}</label>
                                                                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="replacement-base-price-{{ data_get($rule, 'id') }}" name="base_price_egp" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" value="{{ $isReplacementFormContext ? old('base_price_egp', $formatEgpInput(data_get($rule, 'base_price_minor', 0))) : $formatEgpInput(data_get($rule, 'base_price_minor', 0)) }}" required>
                                                                    @if ($isReplacementFormContext)
                                                                        @error('base_price_egp')
                                                                            <p class="mt-1 text-sm text-[var(--pn-danger)]" id="replacement-base-price-error-{{ data_get($rule, 'id') }}">{{ $message }}</p>
                                                                        @enderror
                                                                    @endif
                                                                </div>
                                                                <div>
                                                                    <label class="block text-sm font-semibold" for="replacement-overtime-price-{{ data_get($rule, 'id') }}">{{ __('pricing.replacement_overtime_price_label') }}</label>
                                                                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="replacement-overtime-price-{{ data_get($rule, 'id') }}" name="overtime_price_egp" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" value="{{ $isReplacementFormContext ? old('overtime_price_egp', $formatEgpInput(data_get($rule, 'overtime_price_minor', 0))) : $formatEgpInput(data_get($rule, 'overtime_price_minor', 0)) }}" required>
                                                                    @if ($isReplacementFormContext)
                                                                        @error('overtime_price_egp')
                                                                            <p class="mt-1 text-sm text-[var(--pn-danger)]" id="replacement-overtime-price-error-{{ data_get($rule, 'id') }}">{{ $message }}</p>
                                                                        @enderror
                                                                    @endif
                                                                </div>
                                                                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('pricing.replacement_submit') }}</button>
                                                            </form>
                                                        </div>
                                                    </details>
                                                @else
                                                    <span class="text-sm text-[var(--pn-ink-muted)]">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <aside class="lg:sticky lg:top-24">
                @if ($canManage ?? false)
                    <section class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="create-pricing-heading">
                        <h2 class="text-lg font-bold" id="create-pricing-heading">{{ __('pricing.create_heading') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]" id="create-pricing-help">{{ __('pricing.create_description') }}</p>
                        <p class="mt-3 text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('pricing.manage_only_notice') }}</p>

                        <form class="mt-6 space-y-5" method="POST" action="{{ route('pricing.store') }}" aria-describedby="{{ $errors->any() ? 'pricing-errors create-pricing-help' : 'create-pricing-help' }}">
                            @csrf
                            <input type="hidden" name="form_context" value="pricing-create">
                            <div>
                                <label class="block text-sm font-semibold" for="pricing-branch-create">{{ __('pricing.branch_select_label') }}</label>
                                <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-branch-create" name="branch_id" required @error('branch_id') aria-invalid="true" aria-describedby="pricing-branch-error" @enderror>
                                    <option value="">{{ __('pricing.branch_select_label') }}</option>
                                    @foreach ($manageableBranchList as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranchId) === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="pricing-branch-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="pricing-code">{{ __('pricing.code_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-code" name="code" type="text" value="{{ old('code') }}" autocomplete="off" maxlength="50" required aria-describedby="pricing-code-help{{ $errors->has('code') ? ' pricing-code-error' : '' }}" @error('code') aria-invalid="true" @enderror>
                                <p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="pricing-code-help">{{ __('pricing.code_hint') }}</p>
                                @error('code')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="pricing-code-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="pricing-name">{{ __('pricing.name_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-name" name="name" type="text" value="{{ $oldFormContext === 'pricing-create' ? old('name') : '' }}" maxlength="190" required @if ($oldFormContext === 'pricing-create' && $errors->has('name')) aria-invalid="true" aria-describedby="pricing-name-error" @endif>
                                @if ($oldFormContext === 'pricing-create')
                                    @error('name')
                                        <p class="mt-1 text-sm text-[var(--pn-danger)]" id="pricing-name-error">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="pricing-duration">{{ __('pricing.duration_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-duration" name="base_duration_minutes" type="number" min="1" step="1" value="{{ $oldFormContext === 'pricing-create' ? old('base_duration_minutes') : '' }}" required aria-describedby="pricing-duration-help{{ $errors->has('base_duration_minutes') ? ' pricing-duration-error' : '' }}" @error('base_duration_minutes') aria-invalid="true" @enderror>
                                <p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="pricing-duration-help">{{ __('pricing.duration_hint') }}</p>
                                @error('base_duration_minutes')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="pricing-duration-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="pricing-base-price">{{ __('pricing.base_price_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-base-price" name="base_price_egp" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" value="{{ $oldFormContext === 'pricing-create' ? old('base_price_egp') : '' }}" required aria-describedby="pricing-base-price-help{{ $errors->has('base_price_egp') ? ' pricing-base-price-error' : '' }}" @error('base_price_egp') aria-invalid="true" @enderror>
                                <p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="pricing-base-price-help">{{ __('pricing.base_price_hint') }}</p>
                                @error('base_price_egp')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="pricing-base-price-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="pricing-overtime-price">{{ __('pricing.overtime_price_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-overtime-price" name="overtime_price_egp" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" value="{{ $oldFormContext === 'pricing-create' ? old('overtime_price_egp') : '' }}" required aria-describedby="pricing-overtime-price-help{{ $errors->has('overtime_price_egp') ? ' pricing-overtime-price-error' : '' }}" @error('overtime_price_egp') aria-invalid="true" @enderror>
                                <p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="pricing-overtime-price-help">{{ __('pricing.overtime_price_hint') }}</p>
                                @error('overtime_price_egp')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="pricing-overtime-price-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <p class="rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-3 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('pricing.tax_snapshot_notice') }}</p>
                            <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('pricing.create') }}</button>
                        </form>
                    </section>
                @else
                    <section class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-5 sm:p-6" aria-labelledby="pricing-view-only-heading">
                        <h2 class="text-lg font-bold" id="pricing-view-only-heading">{{ __('pricing.view_only') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('pricing.view_only_description') }}</p>
                    </section>
                @endif
            </aside>
        </div>
        </div>

        <div id="pricing-tab-types" data-pn-pricing-panel="types" {!! $panelAttributes('types') !!}>
            <section class="mt-8" aria-labelledby="ticket-types-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold" id="ticket-types-heading">{{ __('pricing.ticket_types_heading') }}</h2>
                        <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('pricing.ticket_types_description') }}</p>
                    </div>
                    <span class="text-sm font-semibold text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $ticketTypeList->count() }}</bdi></span>
                </div>
                @if ($ticketTypeList->isEmpty())
                    <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                        <p class="font-semibold">{{ __('pricing.ticket_types_empty') }}</p>
                        <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('pricing.ticket_types_empty_description') }}</p>
                    </div>
                @else
                    <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="ticket-types-heading" tabindex="0">
                        <table class="min-w-full text-start">
                            <caption class="sr-only">{{ __('pricing.ticket_types_table_caption') }}</caption>
                            <thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm"><tr><th class="px-4 py-3 text-start" scope="col">{{ __('pricing.ticket_type_name') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('pricing.branch') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('pricing.ticket_type_price') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('pricing.ticket_type_version') }}</th></tr></thead>
                            <tbody class="divide-y divide-[var(--pn-border)]">
                                @foreach ($ticketTypeList as $type)
                                    <tr class="align-top"><th class="px-4 py-4 text-start" data-label="{{ __('pricing.ticket_type_name') }}" scope="row"><span class="block font-semibold" dir="auto">{{ $type->name }}</span><span class="mt-1 block font-mono text-sm text-[var(--pn-ink-muted)]" dir="ltr"><bdi>{{ $type->code }}</bdi></span></th><td class="px-4 py-4" data-label="{{ __('pricing.branch') }}">{{ $type->branch?->name ?? __('pricing.all_branches') }}</td><td class="px-4 py-4 tabular-nums" data-label="{{ __('pricing.ticket_type_price') }}"><bdi dir="ltr">{{ $formatEgp($type->price_minor) }} {{ $type->currency }}</bdi></td><td class="px-4 py-4 tabular-nums" data-label="{{ __('pricing.ticket_type_version') }}"><bdi dir="ltr">{{ __('pricing.version', ['version' => $type->pricingRule?->version ?? '—']) }}</bdi></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="mt-8 max-w-3xl" aria-labelledby="ticket-type-create-heading">
                @if ($canManage ?? false)
                    <details class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" id="ticket-type-create" @if ($typeFormOpen) open @endif>
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-lg font-bold focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]"><span id="ticket-type-create-heading">{{ __('pricing.ticket_type_create_heading') }}</span><span aria-hidden="true" class="text-xl">+</span></summary>
                        <div class="border-t border-[var(--pn-border)] p-5 sm:p-6"><p class="text-sm leading-6 text-[var(--pn-ink-muted)]" id="ticket-type-create-help">{{ __('pricing.ticket_type_create_description') }}</p>
                            <form class="mt-5 grid gap-5 sm:grid-cols-2" method="POST" action="{{ route('ticket-types.store') }}" aria-describedby="ticket-type-create-help" data-pn-submit>@csrf<input type="hidden" name="form_context" value="{{ $typeFormContext }}">
                                <div><label class="block text-sm font-semibold" for="pricing-ticket-type-branch">{{ __('pricing.branch_select_label') }}</label><select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-ticket-type-branch" name="branch_id" required><option value="">{{ __('pricing.branch_select_label') }}</option>@foreach ($manageableBranchList as $branch)<option value="{{ $branch->id }}" @selected($typeBranchId === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select>@error('branch_id')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror</div>
                                <div><label class="block text-sm font-semibold" for="pricing-ticket-type-rule">{{ __('pricing.ticket_type_rule_label') }}</label><select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-ticket-type-rule" name="pricing_rule_id" required><option value="">{{ __('pricing.ticket_type_rule_placeholder') }}</option>@foreach ($ruleList as $rule)<option value="{{ $rule->id }}" data-branch-id="{{ $rule->branch_id }}" dir="auto">{{ $rule->name }} · <bdi dir="ltr">{{ $rule->code }}</bdi></option>@endforeach</select>@error('pricing_rule_id')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror</div>
                                <div><label class="block text-sm font-semibold" for="pricing-ticket-type-code">{{ __('pricing.ticket_type_code_label') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 font-mono uppercase focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pricing-ticket-type-code" name="code" type="text" value="{{ $oldFormContext === $typeFormContext ? old('code') : '' }}" maxlength="50" required>@error('code')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror</div>
                                <div><label class="block text-sm font-semibold" for="pricing-ticket-type-name">{{ __('pricing.ticket_type_name_label') }}</label><input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3" id="pricing-ticket-type-name" name="name" type="text" value="{{ $oldFormContext === $typeFormContext ? old('name') : '' }}" maxlength="190" required dir="auto">@error('name')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror</div>
                                <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] sm:col-span-2" type="submit" data-pn-loading-label="{{ __('pricing.ticket_type_create_loading') }}">{{ __('pricing.ticket_type_create_submit') }}</button>
                            </form>
                        </div>
                    </details>
                @else
                    <section class="rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface-subtle)] p-5"><h2 class="text-lg font-bold">{{ __('pricing.view_only') }}</h2><p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('pricing.ticket_types_view_only') }}</p></section>
                @endif
            </section>
        </div>

    </main>
@endsection
