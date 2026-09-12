@extends('layouts.app')

@section('title', __('platform.login.page_title') . ' · ' . __('platform.brand'))

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
        <section class="w-full max-w-md rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 shadow-sm sm:p-8" aria-labelledby="platform-login-heading">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-6">
                <div>
                    <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('platform.brand') }}</p>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.platform_label') }}</p>
                </div>
                <form class="flex items-end gap-2" method="POST" action="{{ route('locale.store') }}">
                    @csrf
                    <label class="text-sm font-semibold" for="platform-login-locale">{{ __('platform.locale.label') }}</label>
                    <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="platform-login-locale" name="locale">
                        <option value="en" @selected(app()->isLocale('en'))>{{ __('platform.locale.english') }}</option>
                        <option value="ar" @selected(app()->isLocale('ar'))>{{ __('platform.locale.arabic') }}</option>
                    </select>
                    <button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('platform.locale.change') }}</button>
                </form>
            </header>

            @if (session('status') || session('status_message') || session('success'))
                <div class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] px-4 py-3 text-sm font-semibold text-[var(--pn-primary)]" role="status" aria-live="polite">
                    {{ session('status') ?? session('status_message') ?? session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] px-4 py-3 text-[var(--pn-danger)]" role="alert" tabindex="-1">
                    <p class="font-semibold">{{ __('platform.login.validation_failed') }}</p>
                    <ul class="mt-2 list-inside list-disc text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <h1 class="mt-6 text-2xl font-bold" id="platform-login-heading">{{ __('platform.login.heading') }}</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('platform.login.description') }}</p>

            <form class="mt-6 space-y-5" method="POST" action="{{ route('platform.login.store') }}">
                @csrf
                <div>
                    <label class="block text-sm font-semibold" for="platform-email">{{ __('platform.login.email_label') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="platform-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required @error('email') aria-invalid="true" aria-describedby="platform-email-error" @enderror>
                    @error('email')
                        <p class="mt-2 text-sm text-[var(--pn-danger)]" id="platform-email-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="platform-password">{{ __('platform.login.password_label') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="platform-password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="platform-password-error" @enderror>
                    @error('password')
                        <p class="mt-2 text-sm text-[var(--pn-danger)]" id="platform-password-error">{{ $message }}</p>
                    @enderror
                </div>
                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('platform.login.submit') }}</button>
            </form>

            <p class="mt-6 border-t border-[var(--pn-border)] pt-5 text-sm text-[var(--pn-ink-muted)]">{{ __('platform.login.access_note') }}</p>
        </section>
    </main>
@endsection
