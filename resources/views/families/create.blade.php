@extends('layouts.app')

@section('title', __('families.create_title').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-4xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('families.index') }}">{{ __('families.back_to_families') }}</a>
            <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('families.create_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('families.create_description') }}</p>
        </header>

        @if ($errors->any())
            <div class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="family-form-errors" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('families.form_error') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('existing_family_url'))
            <a class="mt-4 inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ session('existing_family_url') }}">{{ __('families.view_existing') }}</a>
        @endif

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="family-form-heading">
            <h2 class="text-lg font-bold" id="family-form-heading">{{ __('families.form_caption') }}</h2>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('families.operational_notice') }}</p>

            <form class="mt-6 space-y-8" method="POST" action="{{ route('families.store') }}" aria-describedby="{{ $errors->any() ? 'family-form-errors' : 'family-form-heading' }}">
                @csrf

                <fieldset class="space-y-5">
                    <legend class="text-base font-bold">{{ __('families.guardian_section') }}</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold" for="guardian-name">{{ __('families.guardian_name_label') }}</label>
                            <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="guardian-name" name="guardian_name" type="text" value="{{ old('guardian_name') }}" autocomplete="name" maxlength="190" required @error('guardian_name') aria-invalid="true" aria-describedby="guardian-name-error" @enderror>
                            @error('guardian_name')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="guardian-name-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold" for="guardian-phone">{{ __('families.phone_label') }}</label>
                            <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="guardian-phone" name="phone" type="tel" value="{{ old('phone', $suggestedPhone) }}" autocomplete="tel" inputmode="tel" maxlength="30" required dir="ltr" @error('phone') aria-invalid="true" aria-describedby="guardian-phone-error" @enderror>
                            @error('phone')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="guardian-phone-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold" for="guardian-email">{{ __('families.email_label') }}</label>
                            <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="guardian-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" maxlength="190" @error('email') aria-invalid="true" aria-describedby="guardian-email-error" @enderror>
                            @error('email')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="guardian-email-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold" for="preferred-locale">{{ __('families.preferred_locale_label') }}</label>
                            <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="preferred-locale" name="preferred_locale" required @error('preferred_locale') aria-invalid="true" aria-describedby="preferred-locale-error" @enderror>
                                <option value="ar" @selected(old('preferred_locale', app()->isLocale('ar') ? 'ar' : 'en') === 'ar')>{{ __('families.locale_ar') }}</option>
                                <option value="en" @selected(old('preferred_locale', app()->isLocale('ar') ? 'ar' : 'en') === 'en')>{{ __('families.locale_en') }}</option>
                            </select>
                            @error('preferred_locale')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="preferred-locale-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-5 border-t border-[var(--pn-border)] pt-7">
                    <legend class="text-base font-bold">{{ __('families.child_section') }}</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold" for="child-name">{{ __('families.child_name_label') }}</label>
                            <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="child-name" name="child_name" type="text" value="{{ old('child_name') }}" autocomplete="off" maxlength="190" required @error('child_name') aria-invalid="true" aria-describedby="child-name-error" @enderror>
                            @error('child_name')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="child-name-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold" for="child-date-of-birth">{{ __('families.date_of_birth_label') }}</label>
                            <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="child-date-of-birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" autocomplete="bday" @error('date_of_birth') aria-invalid="true" aria-describedby="child-date-of-birth-error" @enderror>
                            @error('date_of_birth')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="child-date-of-birth-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold" for="relationship-type">{{ __('families.relationship_type_label') }}</label>
                            <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="relationship-type" name="relationship_type" required aria-describedby="relationship-type-help{{ $errors->has('relationship_type') ? ' relationship-type-error' : '' }}" @error('relationship_type') aria-invalid="true" @enderror>
                                <option value="">{{ __('families.relationship_type_placeholder') }}</option>
                                @foreach (__('families.relationship_types') as $value => $label)
                                    <option value="{{ $value }}" @selected(old('relationship_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]" id="relationship-type-help">{{ __('families.relationship_type_help') }}</p>
                            @error('relationship_type')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="relationship-type-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-[var(--pn-border)] pt-6">
                    <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('families.index') }}">{{ __('families.back_to_families') }}</a>
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('families.save') }}</button>
                </div>
            </form>
        </section>
    </main>
@endsection
