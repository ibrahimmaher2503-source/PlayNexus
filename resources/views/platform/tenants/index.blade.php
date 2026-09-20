@extends('layouts.platform')

@section('title', __('platform.tenants.page_title') . ' · ' . __('platform.brand'))

@section('content')
    @php
        $tenantList = $tenants ?? [];
        $tenantCount = is_countable($tenantList) ? count($tenantList) : 0;
        $statusLabels = __('platform.tenants.statuses');
        $statusClasses = [
            'pending' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]',
            'active' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-primary)]',
            'suspended' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-danger)]',
            'closed' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]',
        ];
    @endphp

    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="flex flex-wrap items-start justify-between gap-5 border-b border-[var(--pn-border)] pb-5">
            <div>
                <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('platform.brand') }}</p>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.platform_label') }}</p>
                <h1 class="mt-4 text-2xl font-bold">{{ __('platform.tenants.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.tenants.page_description') }}</p>
            </div>
        </header>

        @if (session('success') || session('status_message') || session('status'))
            <div class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] px-4 py-3 font-semibold text-[var(--pn-primary)]" role="status" aria-live="polite">
                {{ session('success') ?? session('status_message') ?? session('status') }}
            </div>
        @endif

        @if (session('owner_invitation_url'))
            <section class="mt-5 rounded-[14px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-5" aria-labelledby="new-owner-link-heading">
                <h2 class="font-bold" id="new-owner-link-heading">{{ __('platform.tenants.invitation_one_time_link') }}</h2>
                <p class="mt-2 text-sm leading-6">{{ __('platform.tenants.invitation_manual_delivery') }}</p>
                <p class="mt-3 overflow-x-auto rounded-[10px] bg-[var(--pn-surface)] p-3 font-mono text-sm"><bdi dir="ltr">{{ session('owner_invitation_url') }}</bdi></p>
            </section>
        @endif

        @if (session('conflict'))
            <div class="mt-5 rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface-subtle)] px-4 py-3 font-semibold text-[var(--pn-ink)]" role="alert" tabindex="-1">
                {{ session('conflict') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] px-4 py-3 text-[var(--pn-danger)]" role="alert" tabindex="-1">
                <p class="font-semibold">{{ __('platform.tenants.validation_failed') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="provision-tenant-heading">
            <h2 class="text-lg font-bold" id="provision-tenant-heading">{{ __('platform.tenants.provision_heading') }}</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.tenants.provision_description') }}</p>

            <form class="mt-6" method="POST" action="{{ route('platform.tenants.store') }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey ?? '') }}">
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach (['internal_identifier', 'name', 'plan_reference', 'initial_owner_name', 'initial_owner_email'] as $field)
                        <div>
                            <label class="block text-sm font-semibold" for="provision-{{ $field }}">{{ __('platform.tenants.' . $field) }}</label>
                            <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="provision-{{ $field }}" name="{{ $field }}" type="{{ $field === 'initial_owner_email' ? 'email' : 'text' }}" value="{{ old($field) }}" @if ($field === 'initial_owner_email') autocomplete="email" @elseif ($field === 'initial_owner_name') autocomplete="name" @endif required @error($field) aria-invalid="true" aria-describedby="provision-{{ $field }}-error" @enderror>
                            @error($field)
                                <p class="mt-2 text-sm text-[var(--pn-danger)]" id="provision-{{ $field }}-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('platform.tenants.idempotency_hint') }}</p>
                    <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('platform.tenants.provision_submit') }}</button>
                </div>
            </form>
        </section>

        <section class="mt-10" aria-labelledby="tenant-accounts-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" id="tenant-accounts-heading">{{ __('platform.tenants.list_heading') }}</h2>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.tenants.list_description') }}</p>
                </div>
                @if ($tenantCount > 0)
                    <p class="text-sm font-semibold text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $tenantCount }}</bdi></p>
                @endif
            </div>

            @if ($tenantCount === 0)
                <div class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)] shadow-sm" role="status">
                    {{ __('platform.tenants.empty') }}
                </div>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="tenant-accounts-heading" tabindex="0" data-pn-admin-table>
                    <table class="w-full text-start">
                        <caption class="sr-only">{{ __('platform.tenants.table_caption') }}</caption>
                        <thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.tenants.internal_identifier') }}</th>
                                <th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.tenants.name') }}</th>
                                <th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.tenants.plan_reference') }}</th>
                                <th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.tenants.status') }}</th>
                                <th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.tenants.lock_version') }}</th>
                                <th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.tenants.status_heading') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($tenantList as $tenant)
                                @php
                                    $tenantId = data_get($tenant, 'id', data_get($tenant, 'internal_identifier'));
                                    $tenantStatus = (string) data_get($tenant, 'status', 'pending');
                                    $tenantStatusLabel = is_array($statusLabels) && array_key_exists($tenantStatus, $statusLabels) ? $statusLabels[$tenantStatus] : __('platform.tenants.status_unknown');
                                    $statusFormId = 'tenant-status-' . $tenantId;
                                @endphp
                                <tr class="align-top">
                                    <th class="whitespace-nowrap px-4 py-4 text-start font-semibold" scope="row" data-label="{{ __('platform.tenants.internal_identifier') }}"><bdi class="pn-bidi" dir="ltr">{{ data_get($tenant, 'internal_identifier') }}</bdi></th>
                                    <td class="px-4 py-4" data-label="{{ __('platform.tenants.name') }}">
                                        @if (app('router')->has('platform.tenants.show'))
                                            <a class="font-semibold text-[var(--pn-primary)] underline decoration-[var(--pn-border-strong)] underline-offset-4 hover:decoration-current focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.tenants.show', $tenantId) }}">{{ data_get($tenant, 'name') }}</a>
                                        @else
                                            {{ data_get($tenant, 'name') }}
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4" data-label="{{ __('platform.tenants.plan_reference') }}"><bdi class="pn-bidi" dir="ltr">{{ data_get($tenant, 'plan_reference') }}</bdi></td>
                                    <td class="px-4 py-4" data-label="{{ __('platform.tenants.status') }}">
                                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses[$tenantStatus] ?? 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]' }}">{{ $tenantStatusLabel }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4" data-label="{{ __('platform.tenants.lock_version') }}"><bdi dir="ltr">{{ data_get($tenant, 'lock_version') }}</bdi></td>
                                    <td class="px-4 py-4" data-label="{{ __('platform.tenants.status_heading') }}">
                                        @if (app('router')->has('platform.tenants.show'))
                                            <a class="mb-2 inline-flex min-h-11 items-center rounded-[10px] px-2 text-sm font-semibold text-[var(--pn-primary)] underline decoration-[var(--pn-border-strong)] underline-offset-4 hover:decoration-current focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.tenants.show', $tenantId) }}">{{ __('platform.tenants.view_details') }}</a>
                                        @endif
                                        <details class="pn-action-details">
                                            <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('platform.tenants.change_status') }}</summary>
                                            <form id="{{ $statusFormId }}" class="mt-3 space-y-3 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-3" method="POST" action="{{ route('platform.tenants.status', $tenantId) }}" data-pn-confirm-form data-pn-confirm-message="{{ __('platform.tenants.confirm_status_change') }}" onsubmit="return window.confirm(this.dataset.pnConfirmMessage)">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="expected_status" value="{{ $tenantStatus }}">
                                                <p class="rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] px-3 py-2 text-sm leading-6 text-[var(--pn-ink)]">{{ __('platform.tenants.status_consequence') }}</p>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <label class="block text-xs font-semibold" for="{{ $statusFormId }}-status">{{ __('platform.tenants.status_label') }}</label>
                                                        <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $statusFormId }}-status" name="status" required>
                                                            @foreach ($statusLabels as $status => $label)
                                                                <option value="{{ $status }}" @selected($tenantStatus === $status)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold" for="{{ $statusFormId }}-reason">{{ __('platform.tenants.reason') }}</label>
                                                    <textarea class="mt-1 block min-h-20 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $statusFormId }}-reason" name="reason" minlength="3" maxlength="500" aria-describedby="{{ $statusFormId }}-reason-hint" required></textarea>
                                                    <span class="mt-1 block text-xs text-[var(--pn-ink-muted)]" id="{{ $statusFormId }}-reason-hint">{{ __('platform.tenants.reason_hint') }}</span>
                                                </div>
                                                <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('platform.tenants.save_status') }}</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (is_object($tenantList) && method_exists($tenantList, 'hasPages') && $tenantList->hasPages())
                <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('platform.tenants.pagination_label') }}">
                    <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('platform.tenants.page_position', ['current' => $tenantList->currentPage(), 'last' => $tenantList->lastPage()]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($tenantList->previousPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $tenantList->previousPageUrl() }}" aria-label="{{ __('platform.tenants.previous_page') }}">{{ __('platform.tenants.previous') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('platform.tenants.previous') }}</span>
                        @endif
                        @if ($tenantList->nextPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $tenantList->nextPageUrl() }}" aria-label="{{ __('platform.tenants.next_page') }}">{{ __('platform.tenants.next') }}</a>
                        @else
                            <span class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border)] px-4 font-semibold text-[var(--pn-ink-muted)] opacity-60" aria-disabled="true">{{ __('platform.tenants.next') }}</span>
                        @endif
                    </div>
                </nav>
            @endif
        </section>

        <dialog class="max-w-md rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-0 text-[var(--pn-ink)] shadow-xl backdrop:bg-[rgb(23_38_43_/_0.48)]" data-pn-confirm-dialog aria-labelledby="platform-confirm-title">
            <div class="p-6">
                <h2 class="text-lg font-bold" id="platform-confirm-title">{{ __('platform.tenants.change_status') }}</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--pn-ink-muted)]" data-pn-confirm-message></p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-confirm-cancel>{{ __('platform.tenants.cancel') }}</button>
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-confirm-submit>{{ __('platform.tenants.save_status') }}</button>
                </div>
            </div>
        </dialog>
    </main>
@endsection
