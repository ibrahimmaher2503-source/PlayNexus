@extends('layouts.app')

@section('title', __('passwords.reset_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4 py-8">
        <section class="w-full rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 shadow-sm">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">PlayNexus</p>
            <h1 class="mt-2 text-2xl font-bold">{{ __('passwords.reset_title') }}</h1>
            <p class="mt-2 text-sm text-[var(--pn-ink-muted)]" id="password-reset-description">{{ __('passwords.reset_description') }}</p>

            @if ($errors->any())
                <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] px-4 py-3 text-sm text-[var(--pn-danger)]" id="password-reset-errors" role="alert" aria-live="assertive">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="mt-6 space-y-5" method="POST" action="{{ route('password.update') }}" aria-describedby="{{ $errors->any() ? 'password-reset-errors' : 'password-reset-description' }}">
                @csrf
                <input name="token" type="hidden" value="{{ $token }}">
                <div>
                    <label class="block text-sm font-semibold" for="email">{{ __('passwords.email') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="password-reset-email-error" @enderror>
                    @error('email')
                        <p class="mt-2 text-sm text-[var(--pn-danger)]" id="password-reset-email-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="password">{{ __('passwords.new_password') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required @error('password') aria-invalid="true" aria-describedby="password-reset-password-error" @enderror>
                    <p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('passwords.validation.password_invalid') }}</p>
                    @error('password')
                        <p class="mt-2 text-sm text-[var(--pn-danger)]" id="password-reset-password-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="password_confirmation">{{ __('passwords.confirm_password') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                </div>
                <button class="min-h-11 w-full rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('passwords.reset_submit') }}</button>
            </form>
        </section>
    </main>
@endsection
