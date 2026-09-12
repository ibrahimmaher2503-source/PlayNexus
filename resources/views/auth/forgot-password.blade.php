@extends('layouts.app')

@section('title', __('passwords.forgot_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4 py-8">
        <section class="w-full rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-semibold text-[var(--pn-primary)]">PlayNexus</p>
                <form class="flex items-end gap-2" method="POST" action="{{ route('locale.store') }}">
                    @csrf
                    <label class="text-sm font-semibold" for="locale">{{ __('Language') }}</label>
                    <select class="min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-2 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="locale" name="locale">
                        <option value="en" @selected(app()->isLocale('en'))>{{ __('English') }}</option>
                        <option value="ar" @selected(app()->isLocale('ar'))>{{ __('Arabic') }}</option>
                    </select>
                    <button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-3 font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('Change') }}</button>
                </form>
            </div>
            <h1 class="mt-2 text-2xl font-bold">{{ __('passwords.forgot_title') }}</h1>
            <p class="mt-2 text-sm text-[var(--pn-ink-muted)]" id="password-forgot-description">{{ __('passwords.forgot_description') }}</p>

            @if (session('status'))
                <p class="mt-5 rounded-[10px] border border-[#275DAD] bg-[#E8F0FC] px-4 py-3 text-sm text-[#275DAD]" role="status" aria-live="polite">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[#FDE8E6] px-4 py-3 text-sm text-[var(--pn-danger)]" id="password-forgot-errors" role="alert" aria-live="assertive">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="mt-6 space-y-5" method="POST" action="{{ route('password.email') }}" aria-describedby="{{ $errors->any() ? 'password-forgot-errors' : 'password-forgot-description' }}">
                @csrf
                <div>
                    <label class="block text-sm font-semibold" for="email">{{ __('passwords.email') }}</label>
                    <input class="mt-2 block min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required @error('email') aria-invalid="true" aria-describedby="password-email-error" @enderror>
                    @error('email')
                        <p class="mt-2 text-sm text-[var(--pn-danger)]" id="password-email-error">{{ $message }}</p>
                    @enderror
                </div>
                <button class="min-h-11 w-full rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('passwords.send_link') }}</button>
            </form>
            <a class="mt-5 inline-block text-sm font-semibold text-[var(--pn-primary)] underline focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('login') }}">{{ __('passwords.back_to_login') }}</a>
        </section>
    </main>
@endsection
