@extends('layouts.app')

@section('title', __('tenant_settings.title').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-4xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}">{{ __('tenant_settings.back') }}</a>
            <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('tenant_settings.title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('tenant_settings.description') }}</p>
        </header>

        @if (session('success'))
            <p class="mt-6 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') }}</p>
        @elseif (session('status_message'))
            <p class="mt-6 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4 font-semibold" role="status" aria-live="polite">{{ session('status_message') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="tenant-settings-errors" role="alert" aria-live="assertive">
                <p class="font-semibold">{{ __('tenant_settings.form_error') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" method="POST" action="{{ route('tenant.settings.update') }}" @if ($errors->any()) aria-describedby="tenant-settings-errors" @endif>
            @csrf
            @method('PATCH')
            <input type="hidden" name="expected_lock_version" value="{{ $tenant->lock_version }}">
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach (['name', 'legal_name'] as $field)
                    <div>
                        <label class="block text-sm font-semibold" for="{{ $field }}">{{ __('tenant_settings.'.$field) }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $field }}" name="{{ $field }}" required maxlength="190" value="{{ old($field, $tenant->$field) }}" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                        @error($field)<p class="mt-2 text-sm text-[var(--pn-danger)]" id="{{ $field }}-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label class="block text-sm font-semibold" for="default_locale">{{ __('tenant_settings.locale') }}</label>
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="default_locale" name="default_locale">
                        <option value="en" @selected(old('default_locale', $tenant->default_locale) === 'en')>{{ __('English') }}</option>
                        <option value="ar" @selected(old('default_locale', $tenant->default_locale) === 'ar')>{{ __('Arabic') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="timezone">{{ __('tenant_settings.timezone') }}</label>
                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="timezone" name="timezone" required value="{{ old('timezone', $tenant->timezone) }}" @error('timezone') aria-invalid="true" aria-describedby="timezone-error" @enderror>
                    @error('timezone')<p class="mt-2 text-sm text-[var(--pn-danger)]" id="timezone-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="currency">{{ __('tenant_settings.currency') }}</label>
                    <input class="mt-2 min-h-11 w-full cursor-not-allowed rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] px-3 text-[var(--pn-ink-muted)]" id="currency" name="currency" readonly value="EGP">
                </div>
                <div>
                    <label class="block text-sm font-semibold" for="reason_code">{{ __('tenant_settings.reason') }}</label>
                    <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 outline-none focus:border-[var(--pn-primary)] focus:ring-2 focus:ring-[var(--pn-focus)]" id="reason_code" name="reason_code">
                        <option value="setup_change">{{ __('tenant_settings.setup_change') }}</option>
                        <option value="correction">{{ __('tenant_settings.correction') }}</option>
                    </select>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('tenant_settings.save') }}</button>
            </div>
        </form>
    </main>
@endsection
