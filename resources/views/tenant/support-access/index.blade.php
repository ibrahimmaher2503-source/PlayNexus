@extends('layouts.app')

@section('title', __('platform.support.history_heading') . ' · PlayNexus')

@section('content')
    <main class="mx-auto max-w-[1200px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="border-b border-[var(--pn-border)] pb-5"><p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p><h1 class="mt-2 text-2xl font-bold">{{ __('platform.support.history_heading') }}</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.support.boundary_three') }}</p></header>
        @if ($grants->count() === 0)
            <p class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.support.empty') }}</p>
        @else
            <div class="mt-6 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]" role="region" aria-label="{{ __('platform.support.history_heading') }}" tabindex="0"><table class="w-full text-start"><thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm"><tr><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.scope') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.ticket') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.granted') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.expires') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.state') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.reason') }}</th></tr></thead><tbody class="divide-y divide-[var(--pn-border)]">@foreach ($grants as $grant)<tr class="align-top"><td class="px-4 py-4">{{ __('platform.support.scopes.'.$grant->scope) }}</td><td class="px-4 py-4"><bdi dir="ltr">{{ $grant->support_ticket ?: '—' }}</bdi></td><td class="whitespace-nowrap px-4 py-4 tabular-nums"><bdi dir="ltr">{{ $grant->granted_at?->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</bdi></td><td class="whitespace-nowrap px-4 py-4 tabular-nums"><bdi dir="ltr">{{ $grant->expires_at?->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</bdi></td><td class="px-4 py-4">{{ $grant->revoked_at ? __('platform.support.revoked') : ($grant->isActive() ? __('platform.support.active') : __('platform.support.expired')) }}</td><td class="max-w-prose px-4 py-4">{{ $grant->reason }}</td></tr>@endforeach</tbody></table></div>
        @endif
    </main>
@endsection
