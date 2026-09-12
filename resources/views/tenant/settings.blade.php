@extends('layouts.app')

@section('title', __('tenant_settings.title').' · PlayNexus')

@section('content')
<main class="mx-auto min-h-screen max-w-3xl px-4 py-6 sm:px-6">
    <a class="font-semibold text-[var(--pn-primary)]" href="{{ route('dashboard') }}">{{ __('Back') }}</a>
    <h1 class="mt-4 text-2xl font-bold">{{ __('tenant_settings.title') }}</h1>
    @if (session('success')) <p class="mt-4 rounded border p-3" role="status">{{ session('success') }}</p> @endif
    @if (session('status_message')) <p class="mt-4 rounded border p-3" role="status">{{ session('status_message') }}</p> @endif
    @if ($errors->any()) <div class="mt-4 rounded border p-3" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    <form class="mt-6 space-y-4" method="POST" action="{{ route('tenant.settings.update') }}">
        @csrf @method('PATCH')
        <input type="hidden" name="expected_lock_version" value="{{ $tenant->lock_version }}">
        @foreach (['name', 'legal_name'] as $field)
            <label class="block font-semibold" for="{{ $field }}">{{ __('tenant_settings.'.$field) }}</label>
            <input class="min-h-11 w-full rounded border px-3" id="{{ $field }}" name="{{ $field }}" required maxlength="190" value="{{ old($field, $tenant->$field) }}">
        @endforeach
        <label class="block font-semibold" for="default_locale">{{ __('tenant_settings.locale') }}</label>
        <select class="min-h-11 w-full rounded border px-3" id="default_locale" name="default_locale"><option value="en" @selected(old('default_locale', $tenant->default_locale)==='en')>English</option><option value="ar" @selected(old('default_locale', $tenant->default_locale)==='ar')>العربية</option></select>
        <label class="block font-semibold" for="timezone">{{ __('tenant_settings.timezone') }}</label>
        <input class="min-h-11 w-full rounded border px-3" id="timezone" name="timezone" required value="{{ old('timezone', $tenant->timezone) }}">
        <label class="block font-semibold" for="currency">{{ __('tenant_settings.currency') }}</label>
        <input class="min-h-11 w-full rounded border px-3" id="currency" name="currency" readonly value="EGP">
        <label class="block font-semibold" for="reason_code">{{ __('tenant_settings.reason') }}</label>
        <select class="min-h-11 w-full rounded border px-3" id="reason_code" name="reason_code"><option value="setup_change">{{ __('tenant_settings.setup_change') }}</option><option value="correction">{{ __('tenant_settings.correction') }}</option></select>
        <button class="min-h-11 rounded bg-[var(--pn-primary)] px-5 font-semibold text-white" type="submit">{{ __('tenant_settings.save') }}</button>
    </form>
</main>
@endsection
