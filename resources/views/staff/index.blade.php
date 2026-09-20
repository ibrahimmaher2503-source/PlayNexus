@extends('layouts.app')

@section('title', __('staff.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ __('staff.page_title') }}</h1>
                    <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('staff.page_description') }}</p>
                </div>
                <nav class="flex flex-wrap gap-2" aria-label="{{ __('staff.access_navigation') }}">
                    <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] px-4 font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('staff.index') }}" aria-current="page">{{ __('staff.employees_tab') }}</a>
                    <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('assignments.index') }}">{{ __('staff.access_link') }}</a>
                    @if ($isOwner)
                        <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('roles.index') }}">{{ __('staff.roles_link') }}</a>
                        <a class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" href="{{ route('staff.create') }}">{{ __('staff.add_link') }}</a>
                    @endif
                </nav>
            </div>
        </header>

        @if (session('success'))
            <div class="mt-6 rounded-[14px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 text-[var(--pn-success)]" role="status" aria-live="polite">
                {{ session('success') }}
            </div>
        @elseif (session('status_message'))
            <div class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4 text-[var(--pn-ink)]" role="status" aria-live="polite">
                {{ session('status_message') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="staff-status-errors" role="alert" aria-live="assertive">
                <p class="font-semibold">{{ __('staff.form_error') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8" aria-labelledby="staff-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" id="staff-heading">{{ __('staff.staff_heading') }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('staff.staff_description') }}</p>
                </div>
            </div>

            <form class="mt-5 flex flex-wrap items-end gap-3" method="GET" action="{{ route('staff.index') }}" role="search" aria-labelledby="staff-search-label">
                <div class="min-w-64 flex-1">
                    <label class="block text-sm font-semibold" id="staff-search-label" for="staff-search">{{ __('staff.search_label') }}</label>
                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="staff-search" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="{{ __('staff.search_placeholder') }}" autocomplete="off" dir="auto">
                </div>
                <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('staff.search_submit') }}</button>
                @if ($search !== '')
                    <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('staff.index') }}">{{ __('staff.search_clear') }}</a>
                @endif
            </form>

            @if ($staff->isEmpty())
                <div class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)] shadow-sm" role="status">
                    {{ $search !== '' ? __('staff.search_empty') : __('staff.staff_empty') }}
                </div>
            @else
                @php
                    $statusLabels = __('staff.statuses');
                    $mutableStatusLabels = __('staff.mutable_statuses');
                    $statusClasses = [
                        'active' => 'bg-[var(--pn-success-soft)] text-[var(--pn-success)]',
                        'invited' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-primary)]',
                        'suspended' => 'bg-[var(--pn-danger-soft)] text-[var(--pn-danger)]',
                        'disabled' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]',
                    ];
                    $roleLabels = $roleLabels ?? [];
                @endphp
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="staff-heading" tabindex="0" data-pn-admin-table>
                    <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                        <caption class="sr-only">{{ __('staff.staff_table_caption') }}</caption>
                        <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                            <tr>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_name') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_email') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_status') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_role') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_branches') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('staff.staff_action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($staff as $member)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-4 py-4 font-semibold" data-label="{{ __('staff.staff_name') }}">{{ $member->name }}</td>
                                    <td class="px-4 py-4" data-label="{{ __('staff.staff_email') }}"><bdi class="pn-bidi" dir="ltr">{{ $member->email }}</bdi></td>
                                    <td class="px-4 py-4" data-label="{{ __('staff.staff_status') }}">
                                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses[$member->status] ?? 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]' }}">
                                            {{ is_array($statusLabels) && array_key_exists($member->status, $statusLabels) ? $statusLabels[$member->status] : __('staff.status_unknown') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4" data-label="{{ __('staff.staff_role') }}">
                                        @if ($member->is_owner)
                                            {{ __('staff.owner_locked') }}
                                        @elseif ($member->role_code)
                                            {{ $roleLabels[$member->role_code] ?? $member->role_code }}
                                        @else
                                            <span class="text-[var(--pn-ink-muted)]">{{ __('staff.role_unassigned') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4" data-label="{{ __('staff.staff_branches') }}"><bdi dir="ltr">{{ (int) ($member->branches_count ?? 0) }}</bdi></td>
                                    <td class="px-4 py-4" data-label="{{ __('staff.staff_action') }}">
                                        @if ($member->is_owner)
                                            <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('staff.owner_locked') }}</p>
                                        @elseif ((int) $member->id === (int) auth()->id())
                                            <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('staff.self_locked') }}</p>
                                        @else
                                            <div class="flex flex-wrap items-end gap-2" data-pn-row-actions>
                                                @if ($isOwner)
                                                    <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('staff.edit', $member) }}">{{ __('staff.edit_identity') }}</a>
                                                @endif
                                                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('assignments.index', ['user_id' => $member->id]) }}">{{ __('staff.manage_access') }}</a>
                                                <details class="pn-action-details">
                                                    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('staff.change_status') }}</summary>
                                                    <form class="mt-3 flex flex-wrap items-end gap-2 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-3" method="POST" action="{{ route('staff.status', $member) }}" aria-describedby="staff-status-errors" data-pn-confirm-form data-pn-confirm-message="{{ __('staff.confirm_status_change') }}" onsubmit="return window.confirm(this.dataset.pnConfirmMessage)">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input name="expected_status" type="hidden" value="{{ $member->status }}">
                                                        <div>
                                                            <label class="sr-only" for="status-{{ $member->id }}">{{ __('staff.new_status') }}</label>
                                                            <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="status-{{ $member->id }}" name="status" required>
                                                                @foreach ($mutableStatusLabels as $value => $label)
                                                                    <option value="{{ $value }}" @selected(($member->status === 'invited' ? 'active' : $member->status) === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('staff.save') }}</button>
                                                    </form>
                                                </details>
                                            </div>
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

        <dialog class="max-w-md rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-0 text-[var(--pn-ink)] shadow-xl backdrop:bg-[rgb(23_38_43_/_0.48)]" data-pn-confirm-dialog aria-labelledby="staff-confirm-title">
            <div class="p-6">
                <h2 class="text-lg font-bold" id="staff-confirm-title">{{ __('staff.change_status') }}</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--pn-ink-muted)]" data-pn-confirm-message></p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-confirm-cancel>{{ __('staff.cancel') }}</button>
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-confirm-submit>{{ __('staff.save') }}</button>
                </div>
            </div>
        </dialog>
    </main>
@endsection
