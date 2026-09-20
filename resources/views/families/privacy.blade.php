@extends('layouts.app')

@section('title', __('privacy.page_title').' · PlayNexus')

@section('content')
<main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6" data-pn-family-privacy>
    <header class="border-b border-[var(--pn-border)] pb-5">
        <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
        <h1 class="mt-1 text-2xl font-bold">{{ __('privacy.page_title') }}</h1>
        <p class="mt-1 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('privacy.page_description') }}</p>
    </header>

    @if (session('success') || session('status_message'))
        <p class="mt-5 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') ?? session('status_message') }}</p>
    @endif
    @if ($errors->any())
        <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" role="alert" aria-live="assertive" tabindex="-1">
            <p class="font-bold">{{ __('privacy.validation_failed') }}</p>
            <ul class="mt-2 list-disc space-y-1 ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr]" aria-labelledby="retention-baseline">
        <div class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm">
            <h2 class="text-lg font-bold" id="retention-baseline">{{ __('privacy.baseline') }}</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('privacy.baseline_value') }}</p>
        </div>
        <div class="rounded-[14px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-5" role="note">
            <p class="font-bold">{{ __('privacy.dry_run') }}</p>
            <p class="mt-2 text-sm leading-6">{{ __('privacy.destructive_disabled') }}</p>
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,.8fr)]">
        <section class="min-w-0 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" aria-labelledby="dry-run-heading">
            <div class="border-b border-[var(--pn-border)] p-5">
                <h2 class="text-lg font-bold" id="dry-run-heading">{{ __('privacy.dry_run') }}</h2>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('privacy.dry_run_help') }}</p>
            </div>
            @if ($rows->isEmpty())
                <p class="p-6 text-sm text-[var(--pn-ink-muted)]" role="status">{{ __('privacy.no_candidates') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-start text-sm">
                        <thead class="bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]"><tr><th class="px-4 py-3 text-start">{{ __('privacy.guardian') }}</th><th class="px-4 py-3 text-start">{{ __('privacy.last_activity') }}</th><th class="px-4 py-3 text-start">{{ __('privacy.eligible_at') }}</th><th class="px-4 py-3 text-start">{{ __('privacy.retention_state') }}</th></tr></thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-4 py-4 font-semibold">{{ $row['guardian']->full_name }}</td>
                                <td class="px-4 py-4" dir="ltr">{{ $row['last_activity_at']?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-4" dir="ltr">{{ $row['eligible_at']?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-4"><span class="rounded-full border px-3 py-1 font-semibold">{{ $row['blocked'] ? __('privacy.blocked_by_hold') : ($row['eligible'] ? __('privacy.eligible') : __('privacy.not_due')) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm" aria-labelledby="place-hold-heading">
            <h2 class="text-lg font-bold" id="place-hold-heading">{{ __('privacy.place_hold') }}</h2>
            <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('privacy.place_hold_help') }}</p>
            <form class="mt-5 space-y-4" method="POST" action="{{ route('families.privacy.holds.store') }}">
                @csrf
                <div><label class="block text-sm font-semibold" for="privacy-guardian">{{ __('privacy.select_guardian') }}</label><select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="privacy-guardian" name="guardian_id" required><option value="">—</option>@foreach ($holdCandidates as $candidate)<option value="{{ $candidate->id }}" @selected((string) old('guardian_id') === (string) $candidate->id)>{{ $candidate->full_name }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-semibold" for="privacy-category">{{ __('privacy.category') }}</label><select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="privacy-category" name="category" required>@foreach (__('privacy.categories') as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-semibold" for="privacy-reason">{{ __('privacy.reason') }}</label><textarea class="mt-2 min-h-28 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="privacy-reason" name="reason" minlength="10" maxlength="500" required aria-describedby="privacy-reason-help">{{ old('reason') }}</textarea><p class="mt-1 text-xs text-[var(--pn-ink-muted)]" id="privacy-reason-help">{{ __('privacy.reason_help') }}</p></div>
                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-bold text-white hover:bg-[var(--pn-primary-strong)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('privacy.confirm_hold') }}</button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm" aria-labelledby="active-holds-heading">
        <h2 class="text-lg font-bold" id="active-holds-heading">{{ __('privacy.active_holds') }}</h2>
        <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('privacy.active_holds_help') }}</p>
        @if ($activeHolds->isEmpty())
            <p class="mt-4 rounded-[10px] bg-[var(--pn-surface-subtle)] p-4 text-sm text-[var(--pn-ink-muted)]" role="status">{{ __('privacy.no_holds') }}</p>
        @else
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($activeHolds as $hold)
                    <article class="rounded-[12px] border border-[var(--pn-border)] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-bold">{{ $hold->guardian->full_name }}</h3><p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('privacy.categories.'.$hold->category) }}</p></div><span class="rounded-full border border-[var(--pn-warning)] px-3 py-1 text-sm font-semibold">{{ __('privacy.blocked_by_hold') }}</span></div>
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2"><div><dt class="text-[var(--pn-ink-muted)]">{{ __('privacy.placed_by') }}</dt><dd class="font-semibold">{{ $hold->placedBy->name }}</dd></div><div><dt class="text-[var(--pn-ink-muted)]">{{ __('privacy.placed_at') }}</dt><dd class="font-semibold" dir="ltr">{{ $hold->placed_at->format('Y-m-d H:i') }}</dd></div></dl>
                        <form class="mt-4 border-t border-[var(--pn-border)] pt-4" method="POST" action="{{ route('families.privacy.holds.release', $hold) }}">@csrf @method('PATCH')<label class="block text-sm font-semibold" for="release-reason-{{ $hold->id }}">{{ __('privacy.release_reason') }}</label><textarea class="mt-2 min-h-20 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="release-reason-{{ $hold->id }}" name="release_reason" minlength="10" maxlength="500" required></textarea><button class="mt-3 inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-danger)] px-4 font-bold text-[var(--pn-danger)] hover:bg-[var(--pn-danger-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('privacy.release') }}</button></form>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</main>
@endsection
