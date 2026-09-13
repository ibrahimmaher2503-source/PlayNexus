@extends('layouts.app')

@section('title', __('Staff sign in').' · PlayNexus')

@section('content')
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4 py-8">
        <section class="w-full rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-semibold text-[var(--pn-primary)]">PlayNexus</p>
                <form class="flex items-center gap-2" method="POST" action="{{ route('locale.store') }}">
                    @csrf
                    <label class="sr-only" for="locale">{{ __('Language') }}</label>
                    <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2 text-sm focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="locale" name="locale" data-pn-locale-select aria-label="{{ __('Language') }}">
                        <option value="en" @selected(app()->isLocale('en'))>{{ __('English') }}</option>
                        <option value="ar" @selected(app()->isLocale('ar'))>{{ __('Arabic') }}</option>
                    </select>
                    <noscript><button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('Change') }}</button></noscript>
                </form>
            </div>
            <h1 class="mt-2 text-2xl font-bold">{{ __('Staff sign in') }}</h1>
            <p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('Use your staff account to access your assigned branches.') }}</p>

            <form class="mt-6 space-y-5" method="POST" action="{{ route('login.store') }}">
                @csrf
                <div>
                    <label class="block text-sm font-semibold" for="email">{{ __('Email address') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
                    @error('email')
                        <p class="mt-2 text-sm text-[var(--pn-danger)]" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="password">{{ __('Password') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="password" name="password" type="password" autocomplete="current-password" required>
                    @error('password')
                        <p class="mt-2 text-sm text-[var(--pn-danger)]" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <p class="text-end text-sm"><a class="font-semibold text-[var(--pn-primary)] underline focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('password.request') }}">{{ __('passwords.forgot_link') }}</a></p>
                <button class="min-h-11 w-full rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('Sign in') }}</button>
            </form>
        </section>
    </main>
@endsection
