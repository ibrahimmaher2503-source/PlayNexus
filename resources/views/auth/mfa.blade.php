@extends($user->tenant_id === null ? 'layouts.platform' : 'layouts.app')

@section('title', __('account_security.title').' · PlayNexus')

@section('content')
    <main class="mx-auto max-w-xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('account_security.title') }}</h1>
        @if (session('status'))
            <p class="mt-4 rounded-[10px] border border-[var(--pn-success)] p-3" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-[10px] border border-[var(--pn-danger)] p-3 text-[var(--pn-danger)]" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @if ($recoveryCodes)
            <section class="mt-6" aria-labelledby="recovery-heading">
                <h2 id="recovery-heading" class="text-lg font-bold">{{ __('account_security.recovery') }}</h2>
                <p class="mt-2 text-sm">{{ __('account_security.recovery_help') }}</p>
                <ul class="mt-3 space-y-2 font-mono" data-mfa-recovery>
                    @foreach ($recoveryCodes as $code)<li><bdi dir="ltr">{{ $code }}</bdi></li>@endforeach
                </ul>
                <a class="mt-5 inline-flex min-h-11 items-center font-semibold underline" href="{{ route($user->tenant_id === null ? 'platform.dashboard' : 'dashboard') }}">{{ __('account_security.back') }}</a>
            </section>
        @else
            <h2 class="mt-6 text-lg font-bold">{{ __($enrolled ? 'account_security.required' : 'account_security.setup') }}</h2>
            @if (! $enrolled)
                <p class="mt-2 text-sm">{{ __('account_security.setup_help') }}</p>
                <p class="mt-4 text-sm font-semibold">{{ __('account_security.key') }}</p>
                <p class="mt-1 break-all font-mono" data-mfa-setup-key><bdi dir="ltr">{{ $secret }}</bdi></p>
            @endif
            <form class="mt-6 space-y-5" method="POST" action="{{ route(($user->tenant_id === null ? 'platform.mfa.' : 'account.mfa.').($enrolled ? 'challenge' : 'confirm')) }}">
                @csrf
                @if (! $enrolled)
                    <div>
                        <label for="mfa-password" class="block text-sm font-semibold">{{ __('account_security.password') }}</label>
                        <input id="mfa-password" name="current_password" type="password" autocomplete="current-password" required class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" @error('current_password') aria-invalid="true" aria-describedby="mfa-password-error" @enderror>
                        @error('current_password')<p id="mfa-password-error" class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label for="mfa-code" class="block text-sm font-semibold">{{ __('account_security.code') }}</label>
                    <input id="mfa-code" name="code" type="text" dir="ltr" autocomplete="one-time-code" required maxlength="64" class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" @error('code') aria-invalid="true" aria-describedby="mfa-code-error" @enderror>
                    @error('code')<p id="mfa-code-error" class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)]">{{ __($enrolled ? 'account_security.verify' : 'account_security.confirm') }}</button>
            </form>
        @endif
    </main>
@endsection
