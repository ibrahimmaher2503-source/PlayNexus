@extends('layouts.platform')

@section('title', __('platform.support.page_title') . ' · ' . __('platform.brand'))

@section('content')
    @php($grantList = $grants ?? [])
    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-danger)]">{{ __('platform.support.exception_label') }}</p>
            <h1 class="mt-2 text-2xl font-bold">{{ __('platform.support.page_title') }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.support.description') }}</p>
        </header>

        @if (session('success') || session('status'))
            <div class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-success-soft)] px-4 py-3 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') ?? session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] px-4 py-3 text-[var(--pn-danger)]" role="alert" tabindex="-1">
                <p class="font-semibold">{{ __('platform.support.validation_failed') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.7fr)]" aria-labelledby="support-request-heading">
            <div class="rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] p-5 sm:p-6">
                <h2 class="text-lg font-bold" id="support-request-heading">{{ __('platform.support.request_heading') }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.support.request_description', ['minutes' => $maxDurationMinutes ?? 60]) }}</p>
                <form class="mt-6 space-y-5" method="POST" action="{{ route('platform.support-access.store') }}">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold" for="support-tenant">{{ __('platform.support.tenant') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="support-tenant" name="target_tenant_id" required @error('target_tenant_id') aria-invalid="true" @enderror>
                            <option value="">{{ __('platform.support.choose_tenant') }}</option>
                            @foreach (($tenants ?? []) as $candidate)
                                <option value="{{ data_get($candidate, 'id') }}" @selected((string) old('target_tenant_id') === (string) data_get($candidate, 'id'))>{{ data_get($candidate, 'name') }} · {{ data_get($candidate, 'internal_identifier') }} · {{ __('platform.tenants.statuses.'.data_get($candidate, 'status')) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold" for="support-scope">{{ __('platform.support.scope') }}</label>
                            <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="support-scope" name="scope" required>
                                @foreach (($scopes ?? []) as $scope => $label)
                                    <option value="{{ $scope }}" @selected(old('scope') === $scope)>{{ __('platform.support.scopes.'.$scope) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="support-duration">{{ __('platform.support.duration') }}</label>
                            <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 tabular-nums focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="support-duration" name="duration_minutes" type="number" min="1" max="{{ $maxDurationMinutes ?? 60 }}" value="{{ old('duration_minutes', $maxDurationMinutes ?? 60) }}" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="support-reason">{{ __('platform.support.reason') }}</label>
                        <textarea class="mt-2 block min-h-24 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="support-reason" name="reason" minlength="10" required>{{ old('reason') }}</textarea>
                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('platform.support.reason_hint') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="support-ticket">{{ __('platform.support.ticket') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="support-ticket" name="support_ticket" value="{{ old('support_ticket') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="support-password">{{ __('platform.support.password') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="support-password" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-danger)] px-4 font-semibold text-[var(--pn-surface)] hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('platform.support.authorize') }}</button>
                </form>
            </div>

            <aside class="rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-warning-soft)] p-5" aria-labelledby="support-boundary-heading">
                <h2 class="text-lg font-bold" id="support-boundary-heading">{{ __('platform.support.boundary_heading') }}</h2>
                <ul class="mt-3 space-y-3 text-sm leading-6 text-[var(--pn-ink)]">
                    <li>{{ __('platform.support.boundary_one') }}</li>
                    <li>{{ __('platform.support.boundary_two') }}</li>
                    <li>{{ __('platform.support.boundary_three') }}</li>
                </ul>
            </aside>
        </section>

        <section class="mt-10" aria-labelledby="support-history-heading">
            <h2 class="text-lg font-bold" id="support-history-heading">{{ __('platform.support.history_heading') }}</h2>
            @if (count($grantList) === 0)
                <p class="mt-3 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.support.empty') }}</p>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]" role="region" aria-labelledby="support-history-heading" tabindex="0">
                    <table class="w-full text-start"><caption class="sr-only">{{ __('platform.support.history_heading') }}</caption><thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm"><tr><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.tenant') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.scope') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.expires') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.state') }}</th><th class="px-4 py-3 text-start" scope="col">{{ __('platform.support.actions') }}</th></tr></thead><tbody class="divide-y divide-[var(--pn-border)]">
                        @foreach ($grantList as $grant)
                            @php($grantId = data_get($grant, 'id'))
                            @php($grantActive = $grant->isActive())
                            <tr class="align-top"><th class="px-4 py-4 text-start" scope="row">{{ data_get($grant, 'tenant_name', data_get($grant, 'tenant.name')) }}</th><td class="px-4 py-4">{{ __('platform.support.scopes.'.data_get($grant, 'scope')) }}</td><td class="whitespace-nowrap px-4 py-4 tabular-nums"><bdi dir="ltr">{{ data_get($grant, 'expires_at') }}</bdi></td><td class="px-4 py-4">{{ data_get($grant, 'revoked_at') ? __('platform.support.revoked') : ($grantActive ? __('platform.support.active') : __('platform.support.expired')) }}</td><td class="px-4 py-4"><div class="flex flex-wrap gap-2"><a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.support-access.show', $grantId) }}">{{ __('platform.support.review') }}</a>@if ($grantActive)<details><summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-[var(--pn-danger)] px-3 text-sm font-semibold text-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('platform.support.revoke') }}</summary><form class="mt-2 min-w-64 space-y-3 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-3" method="POST" action="{{ route('platform.support-access.revoke', $grantId) }}">@csrf @method('PATCH')<label class="block text-xs font-semibold">{{ __('platform.support.reason') }}<textarea class="mt-1 block min-h-20 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-2" name="reason" minlength="10" required></textarea></label><label class="block text-xs font-semibold">{{ __('platform.support.password') }}<input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2" name="current_password" type="password" required></label><button class="min-h-11 rounded-[10px] bg-[var(--pn-danger)] px-3 text-sm font-semibold text-[var(--pn-surface)]" type="submit">{{ __('platform.support.confirm_revoke') }}</button></form></details>@endif</div></td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            @endif
        </section>
    </main>
@endsection
