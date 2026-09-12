@extends('layouts.app')

@section('title', __('tenant.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-5xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <div>
                <h1 class="text-2xl font-bold">{{ __('tenant.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('tenant.page_description') }}</p>
            </div>
        </header>

        <section class="mt-8" aria-labelledby="tenant-profile-heading">
            <div class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold" id="tenant-profile-heading">{{ __('tenant.profile_heading') }}</h2>
                <dl class="mt-5">
                    <dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('tenant.name_label') }}</dt>
                    <dd class="mt-1 text-xl font-bold">{{ $tenant->name }}</dd>
                </dl>
            </div>
        </section>

        <section class="mt-8" aria-labelledby="staff-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" id="staff-heading">{{ __('tenant.staff_heading') }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('tenant.staff_description') }}</p>
                </div>
            </div>

            @if ($staff->isEmpty())
                <div class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)] shadow-sm" role="status">
                    {{ __('tenant.staff_empty') }}
                </div>
            @else
                @php
                    $statusClasses = [
                        'active' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-primary)]',
                        'invited' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-primary)]',
                        'suspended' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-danger)]',
                        'disabled' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]',
                    ];
                    $statusLabels = __('tenant.statuses');
                @endphp
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="staff-heading" tabindex="0">
                    <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                        <caption class="sr-only">{{ __('tenant.staff_table_caption') }}</caption>
                        <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                            <tr>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('tenant.staff_name') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('tenant.staff_email') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('tenant.staff_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($staff as $member)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-4 py-4 font-semibold">{{ $member->name }}</td>
                                    <td class="px-4 py-4"><bdi dir="ltr">{{ $member->email }}</bdi></td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses[$member->status] ?? 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]' }}">{{ is_array($statusLabels) && array_key_exists($member->status, $statusLabels) ? $statusLabels[$member->status] : __('tenant.status_unknown') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($staff->hasPages() || $staff->currentPage() > 1)
                <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('tenant.pagination_label') }}">
                    <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('tenant.page_position', ['current' => $staff->currentPage(), 'last' => $staff->lastPage()]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($staff->previousPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $staff->previousPageUrl() }}" aria-label="{{ __('tenant.previous_page') }}">{{ __('tenant.previous') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('tenant.previous') }}</span>
                        @endif
                        @if ($staff->nextPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $staff->nextPageUrl() }}" aria-label="{{ __('tenant.next_page') }}">{{ __('tenant.next') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('tenant.next') }}</span>
                        @endif
                    </div>
                </nav>
            @endif
        </section>
    </main>
@endsection
