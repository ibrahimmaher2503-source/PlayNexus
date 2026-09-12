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

            @if ($staff->isEmpty())
                <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('assignments.staff_empty') }}</p>
            @else
                @php $statuses = __('assignments.statuses'); @endphp
                <form class="mt-4 flex flex-wrap items-end gap-3" method="GET" action="{{ route('assignments.index') }}">
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
                $roles = __('assignments.roles');
            @endphp
            <section class="mt-8" aria-labelledby="branches-heading">
                <div>
                    <h2 class="text-lg font-bold" id="branches-heading">{{ __('assignments.branches_heading') }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('assignments.branches_description') }}</p>
                </div>

                @if ($branches->isEmpty())
                    <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('assignments.branch_empty') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="branches-heading" tabindex="0">
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
                                    <tr class="align-top">
                                        <td class="whitespace-nowrap px-4 py-4 font-semibold">
                                            {{ $branch->name }}
                                            <form id="{{ $row }}-form" method="POST" action="{{ route('assignments.update', [$selectedUser, $branch]) }}">
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
                                                    @foreach (['branch_manager', 'reception_staff', 'cashier'] as $role)
                                                        <option value="{{ $role }}" @selected(($currentRole && in_array($currentRole, ['branch_manager', 'reception_staff', 'cashier'], true) ? $currentRole : 'reception_staff') === $role)>{{ is_array($roles) ? $roles[$role] : $role }}</option>
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
