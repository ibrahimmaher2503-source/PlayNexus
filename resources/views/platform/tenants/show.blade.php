@extends('layouts.platform')

@section('title', __('platform.tenants.detail_title') . ' · ' . __('platform.brand'))

@section('content')
    @php
        $status = (string) $tenant->status;
        $invitationState = $invitation?->accepted_at ? __('platform.tenants.invitation_accepted') : ($invitation?->revoked_at ? __('platform.tenants.invitation_revoked') : ($invitation && $invitation->expires_at->isPast() ? __('platform.tenants.invitation_expired') : __('platform.tenants.invitation_pending')));
    @endphp
    <main class="mx-auto min-h-screen max-w-[1200px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
            <div>
                <a class="text-sm font-semibold text-[var(--pn-primary)] underline underline-offset-4" href="{{ route('platform.tenants.index') }}">{{ __('platform.tenants.back_to_list') }}</a>
                <h1 class="mt-3 text-2xl font-bold">{{ $tenant->name }}</h1>
                <p class="mt-2 text-sm text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $tenant->internal_identifier }}</bdi></p>
            </div>
            <span class="inline-flex rounded-full bg-[var(--pn-surface-subtle)] px-3 py-1 text-sm font-semibold">{{ __('platform.tenants.statuses.'.$status) }}</span>
        </header>

        @if (session('success'))
            <div class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-success-soft)] px-4 py-3 font-semibold text-[var(--pn-success)]" role="status">{{ session('success') }}</div>
        @endif
        @if (session('owner_invitation_url'))
            <section class="mt-5 rounded-[14px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-5" aria-labelledby="one-time-link-heading">
                <h2 class="font-bold" id="one-time-link-heading">{{ __('platform.tenants.invitation_one_time_link') }}</h2>
                <p class="mt-2 text-sm leading-6">{{ __('platform.tenants.invitation_manual_delivery') }}</p>
                <p class="mt-3 overflow-x-auto rounded-[10px] bg-[var(--pn-surface)] p-3 font-mono text-sm"><bdi dir="ltr">{{ session('owner_invitation_url') }}</bdi></p>
            </section>
        @endif
        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] px-4 py-3 text-[var(--pn-danger)]" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <section aria-labelledby="identity-heading">
                <h2 class="text-lg font-bold" id="identity-heading">{{ __('platform.tenants.identity_heading') }}</h2>
                <dl class="mt-4 divide-y divide-[var(--pn-border)] rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-5">
                    <div class="grid gap-1 py-4 sm:grid-cols-2"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.name') }}</dt><dd>{{ $tenant->name }}</dd></div>
                    <div class="grid gap-1 py-4 sm:grid-cols-2"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.internal_identifier') }}</dt><dd><bdi dir="ltr">{{ $tenant->internal_identifier }}</bdi></dd></div>
                    <div class="grid gap-1 py-4 sm:grid-cols-2"><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.created_at') }}</dt><dd><bdi dir="ltr">{{ $tenant->created_at?->timezone('Africa/Cairo')->format('Y-m-d H:i') }}</bdi></dd></div>
                </dl>
            </section>

            <section aria-labelledby="usage-heading">
                <h2 class="text-lg font-bold" id="usage-heading">{{ __('platform.tenants.usage_heading') }}</h2>
                <dl class="mt-4 grid grid-cols-2 gap-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5">
                    <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.branch_count') }}</dt><dd class="mt-1 text-xl font-bold tabular-nums"><bdi dir="ltr">{{ $tenant->branches_count }}</bdi></dd></div>
                    <div><dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.user_count') }}</dt><dd class="mt-1 text-xl font-bold tabular-nums"><bdi dir="ltr">{{ $tenant->users_count }}</bdi></dd></div>
                </dl>
                <h2 class="mt-7 text-lg font-bold">{{ __('platform.tenants.commercial_heading') }}</h2>
                <div class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 text-sm">
                    @if ($tenant->currentSubscription)
                        <p class="font-semibold">{{ $tenant->currentSubscription->plan?->name ?? $tenant->plan_reference }}</p>
                        <p class="mt-1 text-[var(--pn-ink-muted)]">{{ __('platform.tenants.status') }}: {{ $tenant->currentSubscription->status }}</p>
                    @else
                        <p class="text-[var(--pn-ink-muted)]">{{ __('platform.tenants.no_commercial_summary') }}</p>
                    @endif
                </div>
            </section>
        </div>

        <section class="mt-10" aria-labelledby="owner-heading">
            <h2 class="text-lg font-bold" id="owner-heading">{{ __('platform.tenants.owner_heading') }}</h2>
            <div class="mt-4 grid gap-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 md:grid-cols-2">
                <div>
                    <p class="font-semibold">{{ $owner?->name ?? '—' }}</p>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]"><bdi dir="ltr">{{ $owner?->email ?? '—' }}</bdi></p>
                    <p class="mt-3 text-sm">{{ __('platform.tenants.owner_status') }}: <strong>{{ $owner?->status ?? '—' }}</strong></p>
                </div>
                <div>
                    <h3 class="font-bold">{{ __('platform.tenants.invitation_heading') }}</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div><dt class="inline font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.invitation_status') }}:</dt> <dd class="inline">{{ $invitationState }}</dd></div>
                        <div><dt class="inline font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.invitation_delivery') }}:</dt> <dd class="inline"><bdi dir="ltr">{{ $invitation?->delivery_status ?? '—' }}</bdi></dd></div>
                        <div><dt class="inline font-semibold text-[var(--pn-ink-muted)]">{{ __('platform.tenants.invitation_expires') }}:</dt> <dd class="inline"><bdi dir="ltr">{{ $invitation?->expires_at?->timezone('Africa/Cairo')->format('Y-m-d H:i') ?? '—' }}</bdi></dd></div>
                    </dl>
                    @if ($owner?->status === 'invited' && $invitation)
                        <form class="mt-4 space-y-3" method="POST" action="{{ route('platform.tenants.owner-invitation.reissue', $tenant) }}">
                            @csrf
                            <label class="block text-sm font-semibold" for="reissue-reason">{{ __('platform.tenants.reissue_reason') }}</label>
                            <textarea class="min-h-20 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3" id="reissue-reason" name="reason" minlength="10" required>{{ old('reason') }}</textarea>
                            <button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)]" type="submit">{{ __('platform.tenants.invitation_reissue') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <section class="mt-10" aria-labelledby="audit-heading">
            <h2 class="text-lg font-bold" id="audit-heading">{{ __('platform.tenants.audit_heading') }}</h2>
            @if ($auditHistory->isEmpty())
                <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 text-[var(--pn-ink-muted)]">{{ __('platform.tenants.audit_empty') }}</p>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]" tabindex="0">
                    <table class="w-full text-start"><thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm"><tr><th class="px-4 py-3 text-start">{{ __('platform.tenants.audit_action') }}</th><th class="px-4 py-3 text-start">{{ __('platform.tenants.audit_outcome') }}</th><th class="px-4 py-3 text-start">{{ __('platform.tenants.audit_reason') }}</th><th class="px-4 py-3 text-start">{{ __('platform.tenants.audit_time') }}</th></tr></thead><tbody class="divide-y divide-[var(--pn-border)]">@foreach ($auditHistory as $event)<tr><th class="px-4 py-3 text-start font-semibold" scope="row"><bdi dir="ltr">{{ $event->action }}</bdi></th><td class="px-4 py-3">{{ $event->outcome }}</td><td class="px-4 py-3"><bdi dir="ltr">{{ $event->reason_code }}</bdi></td><td class="whitespace-nowrap px-4 py-3"><bdi dir="ltr">{{ $event->occurred_at }}</bdi></td></tr>@endforeach</tbody></table>
                </div>
            @endif
        </section>
    </main>
@endsection
