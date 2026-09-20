@extends('layouts.platform')

@section('title', __('platform_dashboard.title') . ' · ' . __('platform.brand'))

@section('content')
    @php
        $statuses = [
            'active' => ['label' => __('platform_dashboard.active'), 'class' => 'text-[var(--pn-primary)]'],
            'pending' => ['label' => __('platform_dashboard.pending'), 'class' => 'text-[var(--pn-warning)]'],
            'suspended' => ['label' => __('platform_dashboard.suspended'), 'class' => 'text-[var(--pn-danger)]'],
        ];
    @endphp

    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="flex flex-wrap items-end justify-between gap-5 border-b border-[var(--pn-border)] pb-5">
            <div>
                <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('platform.platform_label') }}</p>
                <h1 class="mt-2 text-2xl font-bold">{{ __('platform_dashboard.title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.description') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.tenants.index') }}">{{ __('platform_dashboard.open_tenants') }}</a>
                <a class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" href="{{ route('platform.tenants.index') }}#provision-tenant-heading">{{ __('platform_dashboard.provision_tenant') }}</a>
            </div>
        </header>

        <section class="mt-7" aria-labelledby="status-summary-heading">
            <h2 class="text-lg font-bold" id="status-summary-heading">{{ __('platform_dashboard.status_summary') }}</h2>
            <dl class="mt-3 grid grid-cols-2 gap-px overflow-hidden rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-border)] sm:grid-cols-4">
                <div class="bg-[var(--pn-surface)] p-4 sm:p-5">
                    <dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.tenant_total') }}</dt>
                    <dd class="mt-2 text-2xl font-bold tabular-nums"><bdi dir="ltr">{{ $tenantTotal }}</bdi></dd>
                </div>
                @foreach ($statuses as $status => $details)
                    <div class="bg-[var(--pn-surface)] p-4 sm:p-5">
                        <dt class="text-sm font-semibold {{ $details['class'] }}">{{ $details['label'] }}</dt>
                        <dd class="mt-2 text-2xl font-bold tabular-nums"><bdi dir="ltr">{{ $statusCounts[$status] }}</bdi></dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="mt-8" aria-labelledby="recent-onboarding-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div><h2 class="text-lg font-bold" id="recent-onboarding-heading">{{ __('platform_dashboard.recent_heading') }}</h2><p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.recent_description') }}</p></div>
                <a class="inline-flex min-h-11 items-center text-sm font-semibold text-[var(--pn-primary)] underline decoration-[var(--pn-border-strong)] underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.tenants.index') }}">{{ __('platform_dashboard.open_tenants') }}</a>
            </div>
            @if ($recentTenants->isEmpty())
                <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-sm text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.empty_recent') }}</p>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]" tabindex="0">
                    <table class="w-full text-start"><caption class="sr-only">{{ __('platform_dashboard.table_recent') }}</caption><thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm"><tr><th class="px-4 py-3 text-start" scope="col">{{ __('platform_dashboard.tenant') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform_dashboard.identifier') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform_dashboard.status') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform_dashboard.branches') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform_dashboard.created') }}</th></tr></thead><tbody class="divide-y divide-[var(--pn-border)]">@foreach ($recentTenants as $tenant)<tr><th class="px-4 py-4 text-start" scope="row"><a class="font-semibold text-[var(--pn-primary)] underline decoration-[var(--pn-border-strong)] underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.tenants.show', $tenant) }}"><bdi dir="auto">{{ $tenant->name }}</bdi></a></th><td class="whitespace-nowrap px-4 py-4"><bdi dir="ltr">{{ $tenant->internal_identifier }}</bdi></td><td class="px-4 py-4"><span class="font-semibold {{ $statuses[$tenant->status]['class'] ?? 'text-[var(--pn-ink-muted)]' }}">{{ $statuses[$tenant->status]['label'] ?? __('platform.tenants.status_unknown') }}</span></td><td class="px-4 py-4 tabular-nums"><bdi dir="ltr">{{ $tenant->branches_count }}</bdi></td><td class="whitespace-nowrap px-4 py-4 text-sm text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $tenant->created_at->utc()->format('Y-m-d H:i') }} UTC</bdi></td></tr>@endforeach</tbody></table>
                </div>
            @endif
        </section>

        <section class="mt-10 grid gap-10 xl:grid-cols-2">
            <div aria-labelledby="attention-heading"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold" id="attention-heading">{{ __('platform_dashboard.attention_heading') }}</h2><p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.attention_description') }}</p></div></div>@if ($attentionTenants->isEmpty())<p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-sm text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.empty_attention') }}</p>@else<ul class="mt-4 divide-y divide-[var(--pn-border)] rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]">@foreach ($attentionTenants as $tenant)<li class="flex flex-wrap items-center justify-between gap-3 p-4"><div><a class="font-semibold text-[var(--pn-primary)] underline decoration-[var(--pn-border-strong)] underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.tenants.show', $tenant) }}"><bdi dir="auto">{{ $tenant->name }}</bdi></a><p class="mt-1 text-xs text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $tenant->internal_identifier }}</bdi></p></div><span class="font-semibold {{ $statuses[$tenant->status]['class'] }}">{{ $statuses[$tenant->status]['label'] }}</span></li>@endforeach</ul>@endif</div>
            <div aria-labelledby="support-heading"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold" id="support-heading">{{ __('platform_dashboard.support_heading') }}</h2><p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.support_description') }}</p></div><a class="inline-flex min-h-11 items-center text-sm font-semibold text-[var(--pn-primary)] underline decoration-[var(--pn-border-strong)] underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.support-access.index') }}">{{ __('platform_dashboard.open_support') }}</a></div>@if ($activeSupportGrants->isEmpty())<p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-sm text-[var(--pn-ink-muted)]">{{ __('platform_dashboard.empty_support') }}</p>@else<ul class="mt-4 divide-y divide-[var(--pn-border)] rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]">@foreach ($activeSupportGrants as $grant)<li class="flex flex-wrap items-center justify-between gap-3 p-4"><div><a class="font-semibold text-[var(--pn-primary)] underline decoration-[var(--pn-border-strong)] underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.support-access.show', $grant) }}"><bdi dir="auto">{{ $grant->tenant->name }}</bdi></a><p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('platform.support.scopes.'.$grant->scope) }} · <bdi dir="ltr">{{ $grant->expires_at->utc()->format('Y-m-d H:i') }} UTC</bdi></p></div><a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.support-access.show', $grant) }}">{{ __('platform_dashboard.review_grant') }}</a></li>@endforeach</ul>@endif</div>
        </section>

        <nav class="mt-10 flex flex-wrap gap-3 border-t border-[var(--pn-border)] pt-6" aria-label="{{ __('platform.navigation.label') }}"><a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.plans.index') }}">{{ __('platform_dashboard.open_plans') }}</a><a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.subscriptions.index') }}">{{ __('platform_dashboard.open_subscriptions') }}</a></nav>
    </main>
@endsection
