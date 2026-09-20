@extends('layouts.app')

@section('title', __('subscription.page_title').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-4xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}">{{ __('subscription.back_to_dashboard') }}</a>
            <h1 class="mt-5 text-2xl font-bold">{{ __('subscription.page_title') }}</h1>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('subscription.description') }}</p>
        </header>

        @if ($access->isRestricted())
            <section class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-5 text-[var(--pn-danger)]" role="alert">
                <h2 class="font-bold">{{ __('subscription.restricted_heading') }}</h2>
                <p class="mt-1 text-sm">{{ __('subscription.restricted_description') }}</p>
            </section>
        @elseif ($access->isGrace())
            <section class="mt-6 rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface-subtle)] p-5" role="status">
                <h2 class="font-bold">{{ __('subscription.grace_heading') }}</h2>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('subscription.grace_description') }}</p>
            </section>
        @endif

        <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm" aria-labelledby="subscription-commercial-heading">
            <h2 class="text-lg font-bold" id="subscription-commercial-heading">{{ __('subscription.commercial_heading') }}</h2>
            @if ($subscription)
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('subscription.plan') }}</dt><dd class="mt-1 font-bold">{{ $subscription->plan?->name ?? __('subscription.unknown') }}</dd></div>
                    <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('subscription.status') }}</dt><dd class="mt-1 font-bold">{{ __('subscription.statuses.'.$access->status()) }}</dd></div>
                    <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('subscription.billing_interval') }}</dt><dd class="mt-1">{{ __('subscription.intervals.'.$subscription->billing_interval) }}</dd></div>
                    <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('subscription.price') }}</dt><dd class="mt-1 tabular-nums" dir="ltr">{{ $subscription->price_amount_minor === null ? '—' : number_format($subscription->price_amount_minor / 100, 2).' '.$subscription->price_currency }}</dd></div>
                    @if ($subscription->trial_ends_at)<div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('subscription.trial_ends_at') }}</dt><dd class="mt-1"><time datetime="{{ $subscription->trial_ends_at->toIso8601String() }}" dir="ltr">{{ $subscription->trial_ends_at->format('Y-m-d H:i') }} UTC</time></dd></div>@endif
                    @if ($subscription->current_period_ends_at)<div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('subscription.period_ends_at') }}</dt><dd class="mt-1"><time datetime="{{ $subscription->current_period_ends_at->toIso8601String() }}" dir="ltr">{{ $subscription->current_period_ends_at->format('Y-m-d H:i') }} UTC</time></dd></div>@endif
                    @if ($subscription->grace_ends_at)<div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('subscription.grace_ends_at') }}</dt><dd class="mt-1"><time datetime="{{ $subscription->grace_ends_at->toIso8601String() }}" dir="ltr">{{ $subscription->grace_ends_at->format('Y-m-d H:i') }} UTC</time></dd></div>@endif
                </dl>
            @else
                <p class="mt-3 text-sm text-[var(--pn-ink-muted)]">{{ __('subscription.no_subscription') }}</p>
            @endif
        </section>

        <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm" aria-labelledby="subscription-usage-heading">
            <h2 class="text-lg font-bold" id="subscription-usage-heading">{{ __('subscription.usage_heading') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach (['branches' => __('subscription.branches'), 'users' => __('subscription.users')] as $key => $label)
                    @php($overLimit = $limits[$key] !== null && $usage[$key] > $limits[$key])
                    <div class="rounded-[10px] border p-4 {{ $overLimit ? 'border-[var(--pn-danger)]' : 'border-[var(--pn-border)]' }}">
                        <p class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums" dir="ltr">{{ $usage[$key] }} / {{ $limits[$key] === null ? __('subscription.custom') : $limits[$key] }}</p>
                        @if ($overLimit)<p class="mt-2 text-sm font-semibold text-[var(--pn-danger)]">{{ __('subscription.over_limit') }}</p>@endif
                    </div>
                @endforeach
            </div>
        </section>
    </main>
@endsection
