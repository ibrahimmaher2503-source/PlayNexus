@extends('layouts.app')

@section('title', __('tenant_settings.title').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-4xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('tenant_settings.organization_label') }}</p>
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

        <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-5 sm:p-6" aria-labelledby="organization-summary-heading">
            <h2 class="text-lg font-bold" id="organization-summary-heading">{{ __('tenant_settings.summary_heading') }}</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('tenant_settings.summary_name') }}</dt>
                    <dd class="mt-1 font-semibold">{{ $tenant->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('tenant_settings.summary_timezone') }}</dt>
                    <dd class="mt-1 font-semibold"><bdi dir="ltr">{{ $tenant->timezone }}</bdi></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('tenant_settings.summary_currency') }}</dt>
                    <dd class="mt-1 font-semibold"><bdi dir="ltr">{{ $tenant->currency }}</bdi></dd>
                </div>
            </dl>
        </section>

        <form class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" method="POST" action="{{ route('tenant.settings.update') }}" data-pn-form @if ($errors->any()) aria-describedby="tenant-settings-errors" @endif>
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
            </div>
            <div class="sticky bottom-0 z-10 -mx-2 mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--pn-border)] bg-[color:oklch(0.989_0.004_205_/_0.96)] px-2 py-3 backdrop-blur" data-pn-save-bar>
                <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('tenant_settings.save_hint') }}</p>
                <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('tenant_settings.save') }}</button>
            </div>
        </form>
    </main>
@endsection
