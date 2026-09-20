@extends('layouts.app')

@section('title', __('platform.invitation.page_title') . ' · ' . __('platform.brand'))

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
        <section class="w-full max-w-md rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 shadow-sm sm:p-8" aria-labelledby="invitation-heading">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('platform.brand') }}</p>
            <h1 class="mt-3 text-2xl font-bold" id="invitation-heading">{{ __('platform.invitation.heading') }}</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.invitation.description') }}</p>
            @if ($errors->any())<div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] px-4 py-3 text-sm text-[var(--pn-danger)]" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form class="mt-6 space-y-5" method="POST" action="{{ route('owner-invitations.accept', $token) }}">@csrf
                <div><label class="block text-sm font-semibold" for="invitation-password">{{ __('platform.invitation.password') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="invitation-password" name="password" type="password" autocomplete="new-password" required autofocus></div>
                <div><label class="block text-sm font-semibold" for="invitation-password-confirmation">{{ __('platform.invitation.password_confirmation') }}</label><input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="invitation-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('platform.invitation.submit') }}</button>
            </form>
        </section>
    </main>
@endsection
