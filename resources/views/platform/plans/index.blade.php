@extends('layouts.platform')

@section('title', __('platform.plans.page_title') . ' · ' . __('platform.brand'))

@section('content')
    @php
        $planList = $plans ?? [];
        $statusLabels = __('platform.plans.statuses');
        $statusClasses = [
            'inactive' => 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]',
            'active' => 'bg-[var(--pn-success-soft)] text-[var(--pn-success)]',
        ];
        $money = static fn (mixed $minor): string => number_format(((int) $minor) / 100, 2, '.', ',');
    @endphp

    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="flex flex-wrap items-start justify-between gap-5 border-b border-[var(--pn-border)] pb-5">
            <div>
                <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('platform.brand') }}</p>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.platform_label') }}</p>
                <h1 class="mt-4 text-2xl font-bold">{{ __('platform.plans.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.plans.page_description') }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.subscriptions.index') }}">{{ __('platform.plans.open_subscriptions') }}</a>
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.tenants.index') }}">{{ __('platform.plans.open_tenants') }}</a>
                <form method="POST" action="{{ route('platform.logout') }}">
                    @csrf
                    <button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('platform.plans.sign_out') }}</button>
                </form>
            </div>
        </header>

        @if (session('success'))
            <div class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-success-soft)] px-4 py-3 font-semibold text-[var(--pn-success)]" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] px-4 py-3 text-[var(--pn-danger)]" role="alert" tabindex="-1">
                <p class="font-semibold">{{ __('platform.plans.validation_failed') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="create-plan-heading">
            <h2 class="text-lg font-bold" id="create-plan-heading">{{ __('platform.plans.create_heading') }}</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.plans.create_description') }}</p>
            <form class="mt-6 space-y-5" method="POST" action="{{ route('platform.plans.store') }}">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><label class="block text-sm font-semibold" for="plan-code">{{ __('platform.plans.code') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 uppercase outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-code" name="code" value="{{ old('code') }}" required></div>
                    <div><label class="block text-sm font-semibold" for="plan-name">{{ __('platform.plans.name') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-name" name="name" value="{{ old('name') }}" required></div>
                    <div><label class="block text-sm font-semibold" for="plan-description">{{ __('platform.plans.description') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-description" name="description" value="{{ old('description') }}"></div>
                    <div><label class="block text-sm font-semibold" for="plan-monthly-price">{{ __('platform.plans.monthly_price') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-monthly-price" name="monthly_price" type="number" min="0" step="0.01" value="{{ old('monthly_price') }}" required><p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('platform.plans.price_hint') }}</p></div>
                    <div><label class="block text-sm font-semibold" for="plan-annual-price">{{ __('platform.plans.annual_price') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-annual-price" name="annual_price" type="number" min="0" step="0.01" value="{{ old('annual_price') }}" required></div>
                    <div><label class="block text-sm font-semibold" for="plan-discount">{{ __('platform.plans.annual_discount') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-discount" name="annual_discount_bps" type="number" min="0" max="10000" step="100" value="{{ old('annual_discount_bps', 0) }}"><p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('platform.plans.discount_hint') }}</p></div>
                    <div><label class="block text-sm font-semibold" for="plan-branches-limit">{{ __('platform.plans.branches_limit') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-branches-limit" name="branches_limit" type="number" min="1" value="{{ old('branches_limit') }}"><p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('platform.plans.blank_unlimited') }}</p></div>
                    <div><label class="block text-sm font-semibold" for="plan-users-limit">{{ __('platform.plans.users_limit') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-users-limit" name="users_limit" type="number" min="1" value="{{ old('users_limit') }}"></div>
                    <div class="sm:col-span-2 lg:col-span-1"><label class="block text-sm font-semibold" for="plan-reason">{{ __('platform.plans.reason') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="plan-reason" name="reason" value="{{ old('reason') }}" required><p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('platform.plans.reason_hint') }}</p></div>
                </div>
                <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('platform.plans.create_submit') }}</button>
            </form>
        </section>

        <section class="mt-10" aria-labelledby="plans-heading">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold" id="plans-heading">{{ __('platform.plans.list_heading') }}</h2><p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.plans.list_description') }}</p></div><p class="text-sm font-semibold text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ count($planList) }}</bdi></p></div>
            @if (count($planList) === 0)
                <div class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('platform.plans.empty') }}</div>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="plans-heading" tabindex="0" data-pn-admin-table>
                    <table class="w-full text-start"><caption class="sr-only">{{ __('platform.plans.table_caption') }}</caption><thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm"><tr>
                        <th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.plans.code') }}</th><th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.plans.name') }}</th><th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.plans.monthly_price') }}</th><th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.plans.annual_price') }}</th><th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.plans.limits') }}</th><th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.plans.status') }}</th><th class="px-4 py-3 text-start font-semibold" scope="col">{{ __('platform.plans.actions') }}</th>
                    </tr></thead><tbody class="divide-y divide-[var(--pn-border)]">
                        @foreach ($planList as $plan)
                            @php $planId = $plan->getKey(); $limits = $plan->getAttribute('limits_json') ?? []; $planStatus = (string) $plan->getAttribute('status'); $editId = 'plan-edit-'.$planId; @endphp
                            <tr class="align-top"><th class="whitespace-nowrap px-4 py-4 text-start font-semibold" scope="row" data-label="{{ __('platform.plans.code') }}"><bdi dir="ltr">{{ $plan->getAttribute('code') }}</bdi></th><td class="px-4 py-4" data-label="{{ __('platform.plans.name') }}"><strong>{{ $plan->getAttribute('name') }}</strong><span class="mt-1 block text-sm text-[var(--pn-ink-muted)]">{{ $plan->getAttribute('description') }}</span></td><td class="whitespace-nowrap px-4 py-4 tabular-nums" data-label="{{ __('platform.plans.monthly_price') }}"><bdi dir="ltr">{{ $money($plan->getAttribute('monthly_price_minor')) }} EGP</bdi></td><td class="whitespace-nowrap px-4 py-4 tabular-nums" data-label="{{ __('platform.plans.annual_price') }}"><bdi dir="ltr">{{ $money($plan->getAttribute('annual_price_minor')) }} EGP</bdi></td><td class="px-4 py-4 text-sm" data-label="{{ __('platform.plans.limits') }}"><bdi dir="ltr">{{ data_get($limits, 'branches') ?? '∞' }}</bdi> {{ __('platform.plans.branches_short') }} · <bdi dir="ltr">{{ data_get($limits, 'users') ?? '∞' }}</bdi> {{ __('platform.plans.users_short') }}</td><td class="px-4 py-4" data-label="{{ __('platform.plans.status') }}"><span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses[$planStatus] ?? $statusClasses['inactive'] }}">{{ $statusLabels[$planStatus] ?? $planStatus }}</span></td><td class="px-4 py-4" data-label="{{ __('platform.plans.actions') }}"><details class="pn-action-details"><summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('platform.plans.edit') }}</summary>
                                <form id="{{ $editId }}" class="mt-3 min-w-[280px] space-y-3 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-3" method="POST" action="{{ route('platform.plans.update', $planId) }}">@csrf @method('PATCH')
                                    <label class="block text-xs font-semibold" for="{{ $editId }}-name">{{ __('platform.plans.name') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3" id="{{ $editId }}-name" name="name" value="{{ $plan->getAttribute('name') }}" required>
                                    <label class="block text-xs font-semibold" for="{{ $editId }}-description">{{ __('platform.plans.description') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3" id="{{ $editId }}-description" name="description" value="{{ $plan->getAttribute('description') }}">
                                    <input type="hidden" name="code" value="{{ $plan->getAttribute('code') }}">
                                    <div class="grid grid-cols-2 gap-2"><div><label class="block text-xs font-semibold" for="{{ $editId }}-monthly">{{ __('platform.plans.monthly_price') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2" id="{{ $editId }}-monthly" name="monthly_price_minor" type="number" min="0" value="{{ $plan->getAttribute('monthly_price_minor') }}" required></div><div><label class="block text-xs font-semibold" for="{{ $editId }}-annual">{{ __('platform.plans.annual_price') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2" id="{{ $editId }}-annual" name="annual_price_minor" type="number" min="0" value="{{ $plan->getAttribute('annual_price_minor') }}" required></div></div>
                                    <div class="grid grid-cols-3 gap-2"><div><label class="block text-xs font-semibold" for="{{ $editId }}-discount">{{ __('platform.plans.annual_discount') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2" id="{{ $editId }}-discount" name="annual_discount_bps" type="number" min="0" max="10000" value="{{ $plan->getAttribute('annual_discount_bps') ?? 0 }}"></div><div><label class="block text-xs font-semibold" for="{{ $editId }}-branches">{{ __('platform.plans.branches_limit') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2" id="{{ $editId }}-branches" name="branches_limit" type="number" min="1" value="{{ data_get($limits, 'branches') }}"></div><div><label class="block text-xs font-semibold" for="{{ $editId }}-users">{{ __('platform.plans.users_limit') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2" id="{{ $editId }}-users" name="users_limit" type="number" min="1" value="{{ data_get($limits, 'users') }}"></div></div>
                                    <label class="block text-xs font-semibold" for="{{ $editId }}-reason">{{ __('platform.plans.reason') }}</label><input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3" id="{{ $editId }}-reason" name="reason" required>
                                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('platform.plans.save') }}</button>
                                </form>
                                <form class="mt-2 space-y-2 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-3" method="POST" action="{{ route('platform.plans.status', $planId) }}">@csrf @method('PATCH')<input type="hidden" name="expected_status" value="{{ $planStatus }}"><label class="block text-xs font-semibold" for="{{ $editId }}-status">{{ __('platform.plans.status') }}</label><select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3" id="{{ $editId }}-status" name="status"><option value="active" @selected($planStatus === 'active')>{{ $statusLabels['active'] }}</option><option value="inactive" @selected($planStatus === 'inactive')>{{ $statusLabels['inactive'] }}</option></select><input class="min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3" name="reason" placeholder="{{ __('platform.plans.reason') }}" required><button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold" type="submit">{{ __('platform.plans.save_status') }}</button></form>
                            </details></td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            @endif
        </section>
    </main>
@endsection
