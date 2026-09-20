@extends('layouts.platform')

@section('title', __('platform.support.review_title') . ' · ' . __('platform.brand'))

@section('content')
    <main class="mx-auto min-h-screen max-w-[960px] px-4 py-6 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-success-soft)] px-4 py-3 font-semibold text-[var(--pn-success)]" role="status">{{ session('success') }}</div>
        @endif
        <header class="mt-5 border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-danger)]">{{ __('platform.support.exception_label') }}</p>
            <h1 class="mt-2 text-2xl font-bold">{{ __('platform.support.review_title') }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.support.review_description') }}</p>
        </header>

        <section class="mt-8 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="grant-details-heading">
            <h2 class="text-lg font-bold" id="grant-details-heading">{{ __('platform.support.grant_details') }}</h2>
            <dl class="mt-5 grid gap-x-8 gap-y-5 sm:grid-cols-2">
                <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.support.tenant') }}</dt><dd class="mt-1 font-semibold">{{ $tenant->name }} <span class="text-sm font-normal text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $tenant->internal_identifier }}</bdi></span></dd></div>
                <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.support.scope') }}</dt><dd class="mt-1 font-semibold">{{ __('platform.support.scopes.'.$grant->scope) }}</dd></div>
                <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.support.granted') }}</dt><dd class="mt-1 tabular-nums"><bdi dir="ltr">{{ $grant->granted_at?->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</bdi></dd></div>
                <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.support.expires') }}</dt><dd class="mt-1 font-semibold tabular-nums text-[var(--pn-danger)]"><bdi dir="ltr">{{ $grant->expires_at?->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</bdi></dd></div>
                <div class="sm:col-span-2"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.support.reason') }}</dt><dd class="mt-1 max-w-prose whitespace-pre-wrap">{{ $grant->reason }}</dd></div>
            </dl>
        </section>

        @if ($grant->scope === 'tenant_administration_read')
            <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="tenant-summary-heading">
                <h2 class="text-lg font-bold" id="tenant-summary-heading">{{ __('platform.support.tenant_summary') }}</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2"><div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.support.branch_count') }}</dt><dd class="mt-1 text-xl font-bold tabular-nums"><bdi dir="ltr">{{ data_get($summary, 'branches_count') }}</bdi></dd></div><div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.support.user_count') }}</dt><dd class="mt-1 text-xl font-bold tabular-nums"><bdi dir="ltr">{{ data_get($summary, 'users_count') }}</bdi></dd></div></dl>
            </section>
        @elseif ($grant->scope === 'branch_configuration_read')
            <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="branch-configuration-heading">
                <h2 class="text-lg font-bold" id="branch-configuration-heading">{{ __('platform.support.branch_configuration') }}</h2>
                <div class="mt-5 overflow-x-auto" role="region" aria-labelledby="branch-configuration-heading" tabindex="0"><table class="w-full text-start"><thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm"><tr><th class="px-3 py-3 text-start" scope="col">{{ __('platform.support.branch_name') }}</th><th class="px-3 py-3 text-start" scope="col">{{ __('platform.support.branch_code') }}</th><th class="px-3 py-3 text-start" scope="col">{{ __('platform.support.timezone') }}</th><th class="px-3 py-3 text-start" scope="col">{{ __('platform.support.capacity') }}</th><th class="px-3 py-3 text-start" scope="col">{{ __('platform.support.state') }}</th></tr></thead><tbody class="divide-y divide-[var(--pn-border)]">@foreach ($branchConfigurations as $branch)<tr><th class="px-3 py-3 text-start" scope="row">{{ $branch->name }}</th><td class="px-3 py-3"><bdi dir="ltr">{{ $branch->code }}</bdi></td><td class="px-3 py-3"><bdi dir="ltr">{{ $branch->timezone }}</bdi></td><td class="px-3 py-3 tabular-nums"><bdi dir="ltr">{{ $branch->capacity }}</bdi></td><td class="px-3 py-3">{{ $branch->is_active ? __('platform.support.active') : __('platform.support.revoked') }}</td></tr>@endforeach</tbody></table></div>
            </section>
        @endif

        <p class="mt-8"><a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.support-access.index') }}">{{ __('platform.support.back_to_history') }}</a></p>
    </main>
@endsection
