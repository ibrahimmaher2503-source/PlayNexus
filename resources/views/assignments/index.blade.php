@extends('layouts.app')

@section('title', __('assignments.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <div>
                <h1 class="text-2xl font-bold">{{ __('assignments.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('assignments.page_description') }}</p>
            </div>
        </header>

        <nav class="mt-5 flex flex-wrap gap-2" aria-label="{{ __('assignments.hub_label') }}">
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('staff.index') }}">{{ __('assignments.employees_tab') }}</a>
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] px-4 font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('assignments.index') }}" aria-current="page">{{ __('assignments.access_tab') }}</a>
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('roles.index') }}">{{ __('assignments.roles_tab') }}</a>
        </nav>

        @if (session('status'))
            <p class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] px-4 py-3 font-semibold text-[var(--pn-primary)]" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] px-4 py-3 text-[var(--pn-danger)]" role="alert" tabindex="-1">
                <p class="font-semibold">{{ __('assignments.validation_failed') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8" aria-labelledby="staff-heading">
            <div>
                <h2 class="text-lg font-bold" id="staff-heading">{{ __('assignments.staff_heading') }}</h2>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('assignments.staff_description') }}</p>
            </div>

            @php $statuses = __('assignments.statuses'); @endphp
            @if ($staff->isEmpty() && $search === '')
                <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('assignments.staff_empty') }}</p>
            @else
                <form class="mt-4 flex flex-wrap items-end gap-3" method="GET" action="{{ route('assignments.index') }}" role="search" aria-labelledby="staff-search-label">
                    @if ($selectedUser)
                        <input type="hidden" name="user_id" value="{{ $selectedUser->id }}">
                    @endif
                    <div class="min-w-64 flex-1">
                        <label class="block text-sm font-semibold" id="staff-search-label" for="q">{{ __('assignments.staff_search_label') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="q" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="{{ __('assignments.staff_search_placeholder') }}" autocomplete="off" dir="auto">
                    </div>
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('assignments.staff_search_submit') }}</button>
                    @if ($search !== '')
                        <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('assignments.index', $selectedUser ? ['user_id' => $selectedUser->id] : []) }}">{{ __('assignments.staff_search_clear') }}</a>
                    @endif
                </form>

                @if ($staff->isNotEmpty())
                    <div class="mt-5" aria-labelledby="assignment-results-heading">
                        <h3 class="text-sm font-bold" id="assignment-results-heading">{{ __('assignments.results_heading') }}</h3>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($staff as $member)
                                <a class="flex min-h-16 items-center justify-between gap-3 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-4 py-3 hover:border-[var(--pn-primary)] hover:bg-[var(--pn-primary-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('assignments.index', ['user_id' => $member->id, 'q' => $search]) }}" @if ($selectedUser?->is($member)) aria-current="true" @endif>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold">{{ $member->name }}</span>
                                        <span class="mt-1 block truncate text-sm text-[var(--pn-ink-muted)]"><bdi class="pn-bidi" dir="ltr">{{ $member->email }}</bdi></span>
                                    </span>
                                    <span class="shrink-0 text-sm font-semibold text-[var(--pn-primary)]">{{ __('assignments.open_access') }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($staff->isEmpty() && ! $selectedUser)
                    <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('assignments.staff_search_empty') }}</p>
                @endif

                @if ($staff->isNotEmpty() || $selectedUser)
                    <details class="mt-4 pn-action-details">
                        <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('assignments.choose_another') }}</summary>
                        <form class="mt-4 flex flex-wrap items-end gap-3" method="GET" action="{{ route('assignments.index') }}">
                            <input type="hidden" name="q" value="{{ $search }}">
                            <div class="min-w-64 flex-1">
                                <label class="block text-sm font-semibold" for="user_id">{{ __('assignments.picker_label') }}</label>
                                <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="user_id" name="user_id">
                                    <option value="">{{ __('assignments.choose_staff') }}</option>
                                    @if ($selectedUser && ! $staff->contains(fn ($member): bool => $member->is($selectedUser)))
                                        <option value="{{ $selectedUser->id }}" selected>{{ $selectedUser->name }} · <bdi dir="ltr">{{ $selectedUser->email }}</bdi> · {{ is_array($statuses) && isset($statuses[$selectedUser->status]) ? $statuses[$selectedUser->status] : $selectedUser->status }}</option>
                                    @endif
                                    @foreach ($staff as $member)
                                        <option value="{{ $member->id }}" @selected($selectedUser?->is($member))>{{ $member->name }} · <bdi dir="ltr">{{ $member->email }}</bdi> · {{ is_array($statuses) && isset($statuses[$member->status]) ? $statuses[$member->status] : $member->status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('assignments.choose_staff') }}</button>
                        </form>
                    </details>
                @endif
            @endif

            @if ($staff->hasPages() || $staff->currentPage() > 1)
                <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('assignments.pagination_label') }}">
                    <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('assignments.page_position', ['current' => $staff->currentPage(), 'last' => $staff->lastPage()]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($staff->previousPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $staff->previousPageUrl() }}" aria-label="{{ __('assignments.previous_page') }}">{{ __('assignments.previous') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('assignments.previous') }}</span>
                        @endif
                        @if ($staff->nextPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $staff->nextPageUrl() }}" aria-label="{{ __('assignments.next_page') }}">{{ __('assignments.next') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60">{{ __('assignments.next') }}</span>
                        @endif
                    </div>
                </nav>
            @endif
        </section>

        @if ($selectedUser)
            @php
                $roles = array_merge(__('assignments.roles'), $customRoles->all());
                $assignableRoles = array_merge(
                    array_intersect_key(__('assignments.roles'), array_flip(['branch_manager', 'reception_staff', 'cashier'])),
                    $customRoles->all(),
                );
            @endphp
            <section class="mt-8" aria-labelledby="branches-heading">
                <div>
                    <h2 class="text-lg font-bold" id="branches-heading">{{ __('assignments.selected_heading', ['name' => $selectedUser->name]) }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('assignments.selected_description') }}</p>
                </div>

                @if ($selectedUser->status !== 'active')
                    <p class="mt-4 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-4 text-sm text-[var(--pn-warning)]" role="status">{{ __('assignments.inactive_target') }}</p>
                @endif

                @if ($branches->isEmpty())
                    <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('assignments.branch_empty') }}</p>
                @else
                    <div class="mt-4 space-y-4 md:hidden">
                        @foreach ($branches as $branch)
                            @php
                                $current = $assignments->get($branch->id);
                                $currentRole = $current?->role;
                                $currentActive = $current ? (bool) $current->is_active : true;
                                $row = 'mobile-assignment-'.$branch->id;
                            @endphp
                            <form class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-4 shadow-sm" method="POST" action="{{ route('assignments.update', [$selectedUser, $branch]) }}" data-pn-confirm-form data-pn-confirm-when="deactivate" data-pn-confirm-message="{{ __('assignments.confirm_deactivation') }}" onsubmit="return this.elements.is_active.value !== '0' || window.confirm(this.dataset.pnConfirmMessage)">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="expected_role" value="{{ $currentRole ?? '' }}">
                                <input type="hidden" name="expected_is_active" value="{{ $current ? (int) $currentActive : '' }}">
                                <div class="flex items-start justify-between gap-3 border-b border-[var(--pn-border)] pb-3">
                                    <div>
                                        <h3 class="font-bold">{{ $branch->name }}</h3>
                                        <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ $currentRole && array_key_exists($currentRole, $roles) ? $roles[$currentRole] : __('assignments.not_assigned') }}</p>
                                    </div>
                                    <span class="text-sm font-semibold {{ $currentActive && $current ? 'text-[var(--pn-primary)]' : 'text-[var(--pn-ink-muted)]' }}">{{ $current ? ($currentActive ? __('assignments.assigned') : __('assignments.inactive')) : __('assignments.not_assigned') }}</span>
                                </div>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-semibold" for="{{ $row }}-role">{{ __('assignments.new_role') }}</label>
                                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $row }}-role" name="role">
                                            @foreach ($assignableRoles as $role => $roleLabel)
                                                <option value="{{ $role }}" @selected(($currentRole && array_key_exists($currentRole, $assignableRoles) ? $currentRole : 'reception_staff') === $role)>{{ $roleLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold" for="{{ $row }}-active">{{ __('assignments.new_state') }}</label>
                                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $row }}-active" name="is_active">
                                            <option value="1" @selected($currentActive)>{{ __('assignments.active') }}</option>
                                            <option value="0" @selected(! $currentActive)>{{ __('assignments.inactive') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <button class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('assignments.save') }}</button>
                            </form>
                        @endforeach
                    </div>

                    <div class="mt-4 hidden overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm md:block" role="region" aria-labelledby="branches-heading" tabindex="0" data-pn-table>
                        <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                            <caption class="sr-only">{{ __('assignments.branches_heading') }}</caption>
                            <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                                <tr>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('assignments.branch_name') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('assignments.current_role') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('assignments.current_state') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('assignments.new_role') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('assignments.new_state') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col"><span class="sr-only">{{ __('assignments.save') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--pn-border)]">
                                @foreach ($branches as $branch)
                                    @php
                                        $current = $assignments->get($branch->id);
                                        $currentRole = $current?->role;
                                        $currentActive = $current ? (bool) $current->is_active : true;
                                        $row = 'assignment-'.$branch->id;
                                    @endphp
                                    <tr class="align-middle">
                                        <td class="whitespace-nowrap px-4 py-4 font-semibold">
                                            {{ $branch->name }}
                                            <form id="{{ $row }}-form" method="POST" action="{{ route('assignments.update', [$selectedUser, $branch]) }}" data-pn-confirm-form data-pn-confirm-when="deactivate" data-pn-confirm-message="{{ __('assignments.confirm_deactivation') }}" onsubmit="return this.elements.is_active.value !== '0' || window.confirm(this.dataset.pnConfirmMessage)">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="expected_role" value="{{ $currentRole ?? '' }}">
                                                <input type="hidden" name="expected_is_active" value="{{ $current ? (int) $currentActive : '' }}">
                                            </form>
                                        </td>
                                        <td class="px-4 py-4">{{ is_array($roles) && $currentRole && array_key_exists($currentRole, $roles) ? $roles[$currentRole] : ($currentRole ? __('assignments.role_unknown') : __('assignments.not_assigned')) }}</td>
                                        <td class="px-4 py-4">
                                            <span class="font-semibold {{ $currentActive ? 'text-[var(--pn-primary)]' : 'text-[var(--pn-ink-muted)]' }}">{{ $current ? ($currentActive ? __('assignments.assigned') : __('assignments.inactive')) : __('assignments.not_assigned') }}</span>
                                        </td>
                                        <td class="px-4 py-4">
                                                <label class="sr-only" for="{{ $row }}-role">{{ __('assignments.new_role') }}: {{ $branch->name }}</label>
                                                <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $row }}-role" name="role" form="{{ $row }}-form">
                                                    @foreach ($assignableRoles as $role => $roleLabel)
                                                        <option value="{{ $role }}" @selected(($currentRole && array_key_exists($currentRole, $assignableRoles) ? $currentRole : 'reception_staff') === $role)>{{ $roleLabel }}</option>
                                                    @endforeach
                                                </select>
                                        </td>
                                        <td class="px-4 py-4">
                                                <label class="sr-only" for="{{ $row }}-active">{{ __('assignments.new_state') }}: {{ $branch->name }}</label>
                                                <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $row }}-active" name="is_active" form="{{ $row }}-form">
                                                    <option value="1" @selected($currentActive)>{{ __('assignments.active') }}</option>
                                                    <option value="0" @selected(! $currentActive)>{{ __('assignments.inactive') }}</option>
                                                </select>
                                        </td>
                                        <td class="px-4 py-4">
                                                <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit" form="{{ $row }}-form">{{ __('assignments.save') }}</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @else
            <p class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('assignments.choose_staff_hint') }}</p>
        @endif
    </main>
@endsection
