@extends('layouts.app')

@section('title', __('staff.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
            <div>
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}">{{ __('staff.back_to_dashboard') }}</a>
                <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
                <h1 class="mt-1 text-2xl font-bold">{{ __('staff.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('staff.page_description') }}</p>
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

        @if (session('success'))
            <div class="mt-6 rounded-[14px] border border-[#18794E] bg-[#E5F5EC] p-4 text-[#18794E]" role="status" aria-live="polite">
                {{ session('success') }}
            </div>
        @elseif (session('status_message'))
            <div class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4 text-[var(--pn-ink)]" role="status" aria-live="polite">
                {{ session('status_message') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[#FDE8E6] p-4 text-[var(--pn-danger)]" id="staff-status-errors" role="alert" aria-live="assertive">
                <p class="font-semibold">{{ __('staff.form_error') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8" aria-labelledby="staff-heading">
            <div>
                <h2 class="text-lg font-bold" id="staff-heading">{{ __('staff.staff_heading') }}</h2>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('staff.staff_description') }}</p>
            </div>

            @if ($staff->isEmpty())
                <div class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)] shadow-sm" role="status">
                    {{ __('staff.staff_empty') }}
                </div>
            @else
                @php
                    $statusLabels = __('staff.statuses');
                    $mutableStatusLabels = __('staff.mutable_statuses');
                    $reasonLabels = __('staff.reasons');
                    $statusClasses = [
                        'active' => 'bg-[#E5F5EC] text-[#18794E]',
                        'invited' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-primary)]',
                        'suspended' => 'bg-[#FDE8E6] text-[var(--pn-danger)]',
                        'disabled' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]',
                    ];
                @endphp
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="staff-heading" tabindex="0">
                    <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                        <caption class="sr-only">{{ __('staff.staff_table_caption') }}</caption>
                        <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                            <tr>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_name') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_email') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_status') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($staff as $member)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-4 py-4 font-semibold">{{ $member->name }}</td>
                                    <td class="px-4 py-4"><bdi dir="ltr">{{ $member->email }}</bdi></td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses[$member->status] ?? 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]' }}">
                                            {{ is_array($statusLabels) && array_key_exists($member->status, $statusLabels) ? $statusLabels[$member->status] : __('staff.status_unknown') }}
                                        </span>
                                    </td>
                                    <td class="min-w-80 px-4 py-4">
                                        @if ($member->is_owner)
                                            <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('staff.owner_locked') }}</p>
                                        @elseif ((int) $member->id === (int) auth()->id())
                                            <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('staff.self_locked') }}</p>
                                        @else
                                            <form class="space-y-3" method="POST" action="{{ route('staff.status', $member) }}" aria-describedby="staff-status-errors">
                                                @csrf
                                                @method('PATCH')
                                                <input name="expected_status" type="hidden" value="{{ old('expected_status', $member->status) }}">
                                                <div>
                                                    <label class="block text-sm font-semibold" for="status-{{ $member->id }}">{{ __('staff.new_status') }}</label>
                                                    <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="status-{{ $member->id }}" name="status" required>
                                                        @foreach ($mutableStatusLabels as $value => $label)
                                                            <option value="{{ $value }}" @selected(old('status', $member->status === 'invited' ? 'active' : $member->status) === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-semibold" for="reason-{{ $member->id }}">{{ __('staff.reason_code') }}</label>
                                                    <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="reason-{{ $member->id }}" name="reason_code" required>
                                                        <option value="">{{ __('staff.choose_reason') }}</option>
                                                        @foreach ($reasonLabels as $value => $label)
                                                            <option value="{{ $value }}" @selected(old('reason_code') === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('staff.save') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($staff->hasPages() || $staff->currentPage() > 1)
                <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('staff.pagination_label') }}">
                    <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('staff.page_position', ['current' => $staff->currentPage(), 'last' => $staff->lastPage()]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($staff->previousPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $staff->previousPageUrl() }}" aria-label="{{ __('staff.previous_page') }}">{{ __('staff.previous') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('staff.previous') }}</span>
                        @endif
                        @if ($staff->nextPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $staff->nextPageUrl() }}" aria-label="{{ __('staff.next_page') }}">{{ __('staff.next') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('staff.next') }}</span>
                        @endif
                    </div>
                </nav>
            @endif
        </section>
    </main>
@endsection
