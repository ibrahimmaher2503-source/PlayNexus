@extends('layouts.app')

@section('title', __('branch_settings.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
            <div>
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('branches.manage') }}">{{ __('branch_settings.back_to_branches') }}</a>
                <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
                <h1 class="mt-1 text-2xl font-bold">{{ __('branch_settings.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('branch_settings.page_description') }}</p>
            </div>
        </header>

        @if (session('success') || session('status_message'))
            <p class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] px-4 py-3 font-semibold text-[var(--pn-primary)]" role="status">{{ session('success') ?? session('status_message') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] px-4 py-3 text-[var(--pn-danger)]" role="alert" tabindex="-1">
                <p class="font-semibold">{{ __('branch_settings.validation_failed') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5" aria-labelledby="branch-summary-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" id="branch-summary-heading">{{ $branch->name }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]"><span class="font-semibold">{{ __('branch_settings.branch_code') }}:</span> <bdi dir="ltr">{{ $branch->code ?? __('branch_settings.not_set') }}</bdi></p>
                </div>
                <span class="rounded-full border border-[var(--pn-border)] px-3 py-1 text-sm font-semibold">{{ $branch->is_active ? __('branch_settings.active') : __('branch_settings.inactive') }}</span>
            </div>
        </section>

        <nav class="mt-6 overflow-x-auto border-b border-[var(--pn-border)]" aria-label="{{ __('branch_settings.sections_label') }}" data-pn-settings-tabs>
            <div class="flex min-w-max gap-1" role="tablist" aria-label="{{ __('branch_settings.sections_label') }}">
                @foreach (['overview' => __('branch_settings.tabs.overview'), 'operations' => __('branch_settings.tabs.operations'), 'hours' => __('branch_settings.tabs.hours')] as $tab => $label)
                    <a class="inline-flex min-h-11 items-center rounded-t-[10px] border-b-2 border-transparent px-4 text-sm font-semibold text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $tab }}-tab" role="tab" aria-controls="{{ $tab }}-section" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" href="#{{ $tab }}-section">{{ $label }}</a>
                @endforeach
            </div>
        </nav>

        <form class="mt-6 space-y-8" method="POST" action="{{ route('branches.settings.update', $branch) }}" data-pn-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="expected_lock_version" value="{{ $branch->lock_version }}">

            <section class="scroll-mt-24 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5" id="overview-section" role="tabpanel" aria-labelledby="overview-tab" tabindex="0" data-pn-settings-panel>
                <h2 class="text-lg font-bold" id="identity-heading">{{ __('branch_settings.identity_heading') }}</h2>
                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold" for="code">{{ __('branch_settings.code') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 uppercase focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="code" name="code" value="{{ old('code', $branch->code) }}" maxlength="30" autocomplete="off" required>
                        @error('code')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="name">{{ __('branch_settings.name') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="name" name="name" value="{{ old('name', $branch->name) }}" minlength="2" maxlength="190" required>
                        @error('name')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold" for="address_text">{{ __('branch_settings.address_text') }}</label>
                        <textarea class="mt-2 min-h-24 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="address_text" name="address_text" maxlength="1000">{{ old('address_text', $branch->address_text) }}</textarea>
                        @error('address_text')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="scroll-mt-24 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5" id="operations-section" role="tabpanel" aria-labelledby="operations-tab" tabindex="0" data-pn-settings-panel>
                <h2 class="text-lg font-bold" id="operations-heading">{{ __('branch_settings.operations_heading') }}</h2>
                <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="block text-sm font-semibold" for="timezone">{{ __('branch_settings.timezone') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="timezone" name="timezone" value="{{ old('timezone', $branch->timezone) }}" maxlength="64" placeholder="Africa/Cairo" required>
                        @error('timezone')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="capacity">{{ __('branch_settings.capacity') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $branch->capacity) }}" required>
                        @error('capacity')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="currency">{{ __('branch_settings.currency') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 uppercase focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="currency" name="currency" value="{{ old('currency', $branch->currency) }}" maxlength="3" required>
                        @error('currency')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="tax_rate_bps">{{ __('branch_settings.tax_rate_bps') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="tax_rate_bps" name="tax_rate_bps" type="number" min="0" max="10000" value="{{ old('tax_rate_bps', $branch->tax_rate_bps) }}" required>
                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('branch_settings.tax_hint') }}</p>
                        @error('tax_rate_bps')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="tax_mode">{{ __('branch_settings.tax_mode') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="tax_mode" name="tax_mode" required>
                            @foreach (['exclusive' => __('branch_settings.exclusive'), 'inclusive' => __('branch_settings.inclusive')] as $value => $label)
                                <option value="{{ $value }}" @selected(old('tax_mode', $branch->tax_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('tax_mode')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="receipt_prefix">{{ __('branch_settings.receipt_prefix') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 uppercase focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="receipt_prefix" name="receipt_prefix" value="{{ old('receipt_prefix', $branch->receipt_prefix) }}" maxlength="20" required>
                        @error('receipt_prefix')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2 lg:col-span-3">
                        <span class="block text-sm font-semibold">{{ __('branch_settings.payment_methods') }}</span>
                        @php
                            $selectedMethods = old('payment_methods', ['cash']);
                        @endphp
                        <label class="mt-2 inline-flex min-h-11 items-center gap-3 rounded-[10px] border border-[var(--pn-border)] px-3">
                            <input class="h-5 w-5" type="checkbox" name="payment_methods[]" value="cash" @checked(is_array($selectedMethods) && in_array('cash', $selectedMethods, true))>
                            <span>{{ __('branch_settings.cash') }}</span>
                        </label>
                        @error('payment_methods')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                        @error('payment_methods.*')<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('branch_settings.payment_methods_hint') }}</p>
                    </div>
                </div>
            </section>

            <section class="scroll-mt-24 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5" id="hours-section" role="tabpanel" aria-labelledby="hours-tab" tabindex="0" data-pn-settings-panel>
                <h2 class="text-lg font-bold" id="hours-heading">{{ __('branch_settings.hours_heading') }}</h2>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('branch_settings.hours_description') }}</p>
                <div class="mt-4 flex flex-wrap items-end gap-2 rounded-[10px] bg-[var(--pn-surface-subtle)] p-3" aria-label="{{ __('branch_settings.hours_helpers') }}">
                    <div>
                        <span class="block text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('branch_settings.presets') }}</span>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] px-3 text-sm font-semibold hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-hours-preset="weekdays">{{ __('branch_settings.weekdays_preset') }}</button>
                            <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] px-3 text-sm font-semibold hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-hours-preset="weekend">{{ __('branch_settings.weekend_preset') }}</button>
                        </div>
                    </div>
                    <div class="ms-auto flex flex-wrap items-end gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-[var(--pn-ink-muted)]" for="hours-copy-source">{{ __('branch_settings.copy_from') }}</label>
                            <select class="mt-1 min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="hours-copy-source">
                                @foreach ($openingHours as $sourceDay)
                                    <option value="{{ $sourceDay['weekday'] }}">{{ __('branch_settings.weekdays.'.$sourceDay['weekday']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] px-3 text-sm font-semibold hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-hours-copy>{{ __('branch_settings.copy_to_weekdays') }}</button>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-start">
                        <caption class="sr-only">{{ __('branch_settings.hours_heading') }}</caption>
                        <thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm">
                            <tr>
                                <th class="px-3 py-3 text-start font-semibold" scope="col">{{ __('branch_settings.day') }}</th>
                                <th class="px-3 py-3 text-start font-semibold" scope="col">{{ __('branch_settings.day_state') }}</th>
                                <th class="px-3 py-3 text-start font-semibold" scope="col">{{ __('branch_settings.opens_at') }}</th>
                                <th class="px-3 py-3 text-start font-semibold" scope="col">{{ __('branch_settings.closes_at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($openingHours as $day)
                                @php
                                    $rowIndex = $loop->index;
                                    $weekday = $day['weekday'];
                                    $closed = old("opening_hours.$rowIndex.is_closed", $day['is_closed'] ? '1' : '0');
                                    $opensAt = old("opening_hours.$rowIndex.opens_at", $day['opens_at']);
                                    $closesAt = old("opening_hours.$rowIndex.closes_at", $day['closes_at']);
                                @endphp
                                <tr>
                                    <th class="whitespace-nowrap px-3 py-3 text-start font-semibold" scope="row">{{ __('branch_settings.weekdays.'.$weekday) }}</th>
                                    <td class="px-3 py-3">
                                        <input type="hidden" name="opening_hours[{{ $rowIndex }}][weekday]" value="{{ $weekday }}">
                                        <label class="sr-only" for="day-{{ $weekday }}-closed">{{ __('branch_settings.day_state') }}: {{ __('branch_settings.weekdays.'.$weekday) }}</label>
                                        <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="day-{{ $weekday }}-closed" name="opening_hours[{{ $rowIndex }}][is_closed]" data-pn-hours-closed="{{ $weekday }}" required>
                                            <option value="0" @selected((string) $closed === '0')>{{ __('branch_settings.open') }}</option>
                                            <option value="1" @selected((string) $closed === '1')>{{ __('branch_settings.closed') }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-3">
                                        <label class="sr-only" for="day-{{ $weekday }}-opens">{{ __('branch_settings.opens_at') }}: {{ __('branch_settings.weekdays.'.$weekday) }}</label>
                                        <input class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="day-{{ $weekday }}-opens" name="opening_hours[{{ $rowIndex }}][opens_at]" type="time" value="{{ $opensAt }}" data-pn-hours-opens="{{ $weekday }}" step="60">
                                        @error("opening_hours.$rowIndex.opens_at")<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-3 py-3">
                                        <label class="sr-only" for="day-{{ $weekday }}-closes">{{ __('branch_settings.closes_at') }}: {{ __('branch_settings.weekdays.'.$weekday) }}</label>
                                        <input class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="day-{{ $weekday }}-closes" name="opening_hours[{{ $rowIndex }}][closes_at]" type="time" value="{{ $closesAt }}" data-pn-hours-closes="{{ $weekday }}" step="60">
                                        @error("opening_hours.$rowIndex.closes_at")<p class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="sticky bottom-0 z-10 -mx-2 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--pn-border)] bg-[color:oklch(0.989_0.004_205_/_0.96)] px-2 py-3 backdrop-blur" data-pn-save-bar>
                <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('branch_settings.save_hint') }}</p>
                <div class="flex flex-wrap items-center justify-end gap-3">
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('branches.manage') }}">{{ __('branch_settings.cancel') }}</a>
                <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('branch_settings.save') }}</button>
                </div>
            </div>
        </form>
    </main>
@endsection
