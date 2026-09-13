@extends('layouts.app')

@section('title', __('families.profile_title').' · PlayNexus')

@section('content')
    @php
        $relationshipTypes = __('families.relationship_types');
        $familyChildren = collect($children ?? []);
        $oldFormContext = old('form_context');
        $guardianFormOpen = $errors->hasAny(['guardian_name', 'phone', 'email', 'preferred_locale']);
        $childCreateFormOpen = $oldFormContext === 'child-create';
    @endphp

    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('families.index') }}">{{ __('families.back_to_families') }}</a>
            <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('families.profile_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('families.profile_description') }}</p>
        </header>

        @if (session('success'))
            <p class="mt-6 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') }}</p>
        @elseif (session('status_message'))
            <p class="mt-6 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4 font-semibold text-[var(--pn-primary)]" role="status" aria-live="polite">{{ session('status_message') }}</p>
        @endif

        @if (session('conflict') || $errors->has('expected_version'))
            <div class="mt-6 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-4 text-[var(--pn-warning)]" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('families.conflict_title') }}</p>
                <p class="mt-1">{{ session('conflict') ?? $errors->first('expected_version') }}</p>
                <a class="mt-3 inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-warning)] px-4 font-semibold hover:bg-[var(--pn-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('families.show', $guardian) }}">{{ __('families.refresh_profile') }}</a>
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="family-profile-errors" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('families.validation_failed') }}</p>
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

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="family-profile-summary-heading">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="grid size-14 shrink-0 place-items-center rounded-[14px] bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]" aria-hidden="true">
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.8-3.5 3-5.2 6.5-5.2s5.7 1.7 6.5 5.2"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('families.guardian_summary') }}</p>
                        <h2 class="mt-1 truncate text-xl font-bold" id="family-profile-summary-heading">{{ $guardian->full_name }}</h2>
                        <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('families.profile_summary_help') }}</p>
                    </div>
                </div>
                <span class="inline-flex min-h-9 items-center rounded-full border border-[var(--pn-success)] bg-[var(--pn-success-soft)] px-3 text-sm font-semibold text-[var(--pn-success)]">{{ __('families.active') }}</span>
            </div>

            <dl class="mt-6 grid gap-4 border-t border-[var(--pn-border)] pt-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('families.guardian_phone') }}</dt>
                    <dd class="mt-1 font-semibold"><bdi dir="ltr">{{ $guardian->maskedPhone() }}</bdi></dd>
                    <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('families.phone_masked_help') }}</p>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('families.children') }}</dt>
                    <dd class="mt-1 font-semibold">{{ $familyChildren->count() }}</dd>
                </div>
            </dl>
        </section>

        <div class="mt-8 grid items-start gap-8 {{ $canCreateChild ? 'lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]' : '' }}">
            <div class="space-y-8">
                @if ($canUpdateGuardian)
                <details class="group rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="guardian-edit-heading" @if ($guardianFormOpen) open @endif>
                    <summary class="flex min-h-11 cursor-pointer list-none flex-wrap items-start justify-between gap-3 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]">
                        <div>
                            <h2 class="text-lg font-bold" id="guardian-edit-heading">{{ __('families.edit_guardian_heading') }}</h2>
                            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]" id="guardian-edit-help">{{ __('families.edit_guardian_description') }}</p>
                        </div>
                        <span class="rounded-full border border-[var(--pn-border)] px-3 py-1 text-xs font-semibold text-[var(--pn-ink-muted)]"><bdi dir="ltr">v{{ $guardian->lock_version }}</bdi></span>
                        <span aria-hidden="true" class="text-lg leading-none transition-transform group-open:rotate-45">+</span>
                    </summary>

                    <form class="mt-6 space-y-5" method="POST" action="{{ route('families.update', $guardian) }}" aria-describedby="{{ $errors->any() ? 'family-profile-errors guardian-edit-help' : 'guardian-edit-help' }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="expected_version" value="{{ $guardian->lock_version }}">

                        <fieldset class="grid gap-5 sm:grid-cols-2">
                            <legend class="sr-only">{{ __('families.guardian_section') }}</legend>
                            <div>
                                <label class="block text-sm font-semibold" for="guardian-profile-name">{{ __('families.guardian_name_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="guardian-profile-name" name="guardian_name" type="text" value="{{ old('guardian_name', $guardian->full_name) }}" autocomplete="name" maxlength="190" required @error('guardian_name') aria-invalid="true" aria-describedby="guardian-profile-name-error" @enderror>
                                @error('guardian_name')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="guardian-profile-name-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="guardian-profile-phone">{{ __('families.phone_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="guardian-profile-phone" name="phone" type="tel" value="{{ old('phone', $guardian->phone_e164) }}" autocomplete="tel" inputmode="tel" maxlength="30" required dir="ltr" @error('phone') aria-invalid="true" aria-describedby="guardian-profile-phone-error" @enderror>
                                @error('phone')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="guardian-profile-phone-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="guardian-profile-email">{{ __('families.email_label') }}</label>
                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="guardian-profile-email" name="email" type="email" value="{{ old('email', $guardian->email) }}" autocomplete="email" inputmode="email" maxlength="190" @error('email') aria-invalid="true" aria-describedby="guardian-profile-email-error" @enderror>
                                @error('email')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="guardian-profile-email-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold" for="guardian-profile-locale">{{ __('families.preferred_locale_label') }}</label>
                                <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="guardian-profile-locale" name="preferred_locale" required @error('preferred_locale') aria-invalid="true" aria-describedby="guardian-profile-locale-error" @enderror>
                                    <option value="ar" @selected(old('preferred_locale', $guardian->preferred_locale) === 'ar')>{{ __('families.locale_ar') }}</option>
                                    <option value="en" @selected(old('preferred_locale', $guardian->preferred_locale) === 'en')>{{ __('families.locale_en') }}</option>
                                </select>
                                @error('preferred_locale')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="guardian-profile-locale-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </fieldset>

                        <div class="flex justify-end border-t border-[var(--pn-border)] pt-5">
                            <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('families.save_guardian') }}</button>
                        </div>
                    </form>
                </details>
                @endif

                <section aria-labelledby="children-heading">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold" id="children-heading">{{ __('families.children_heading') }}</h2>
                            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('families.children_description') }}</p>
                        </div>
                        <span class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ $familyChildren->count() }} · {{ __('families.children_count_label') }}</span>
                    </div>

                    @if ($familyChildren->isEmpty())
                        <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                            <p class="font-semibold">{{ __('families.no_children') }}</p>
                            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('families.no_children_profile_description') }}</p>
                        </div>
                    @else
                        <div class="mt-4 space-y-4">
                            @foreach ($familyChildren as $child)
                                @php
                                    $childNameError = 'child_name';
                                    $childDateError = 'date_of_birth';
                                    $relationship = data_get($child->pivot, 'relationship_type');
                                    $childFormContext = 'child-update-'.$child->id;
                                    $isChildFormContext = $oldFormContext === $childFormContext;
                                @endphp
                                <article class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="child-heading-{{ $child->id }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-[var(--pn-ink-muted)]">{{ __('families.child_record') }}</p>
                                            <h3 class="mt-1 text-lg font-bold" id="child-heading-{{ $child->id }}">{{ $child->full_name }}</h3>
                                        </div>
                                        <span class="rounded-full border border-[var(--pn-border)] px-3 py-1 text-sm font-semibold">{{ $relationshipTypes[$relationship] ?? $relationship }}</span>
                                    </div>

                                    @if ($canUpdateChild)
                                    <details class="group mt-5 border-t border-[var(--pn-border)] pt-5" @if ($isChildFormContext) open @endif>
                                        <summary class="flex min-h-11 cursor-pointer list-none flex-wrap items-center justify-between gap-3 font-semibold focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]">
                                            <span>{{ __('families.edit_child') }}</span>
                                            <span class="text-sm font-normal text-[var(--pn-ink-muted)]">{{ __('families.edit_child_description') }}</span>
                                            <span aria-hidden="true" class="text-lg leading-none transition-transform group-open:rotate-45">+</span>
                                        </summary>
                                    <form class="mt-4 space-y-5" method="POST" action="{{ route('families.children.update', [$guardian, $child]) }}" aria-describedby="{{ $errors->any() ? 'family-profile-errors child-edit-help-'.$child->id : 'child-edit-help-'.$child->id }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="form_context" value="{{ $childFormContext }}">
                                        <input type="hidden" name="expected_version" value="{{ $child->lock_version }}">
                                        <p class="text-sm text-[var(--pn-ink-muted)]" id="child-edit-help-{{ $child->id }}">{{ __('families.edit_child_description') }}</p>
                                        <div class="grid gap-5 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-sm font-semibold" for="child-{{ $child->id }}-name">{{ __('families.child_name_label') }}</label>
                                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="child-{{ $child->id }}-name" name="child_name" type="text" value="{{ $isChildFormContext ? old($childNameError, $child->full_name) : $child->full_name }}" autocomplete="off" maxlength="190" required @if ($isChildFormContext && $errors->has($childNameError)) aria-invalid="true" aria-describedby="child-{{ $child->id }}-name-error" @endif>
                                                @if ($isChildFormContext)
                                                    @error($childNameError)
                                                        <p class="mt-1 text-sm text-[var(--pn-danger)]" id="child-{{ $child->id }}-name-error">{{ $message }}</p>
                                                    @enderror
                                                @endif
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold" for="child-{{ $child->id }}-dob">{{ __('families.date_of_birth_label') }}</label>
                                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="child-{{ $child->id }}-dob" name="date_of_birth" type="date" value="{{ $isChildFormContext ? old($childDateError, optional($child->date_of_birth)->format('Y-m-d')) : optional($child->date_of_birth)->format('Y-m-d') }}" autocomplete="bday" @if ($isChildFormContext && $errors->has($childDateError)) aria-invalid="true" aria-describedby="child-{{ $child->id }}-dob-error" @endif>
                                                @if ($isChildFormContext)
                                                    @error($childDateError)
                                                        <p class="mt-1 text-sm text-[var(--pn-danger)]" id="child-{{ $child->id }}-dob-error">{{ $message }}</p>
                                                    @enderror
                                                @endif
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold" for="child-{{ $child->id }}-emergency-name">{{ __('families.emergency_contact_name_label') }}</label>
                                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="child-{{ $child->id }}-emergency-name" name="emergency_contact_name" type="text" value="{{ $isChildFormContext ? old('emergency_contact_name', $child->emergency_contact_name) : $child->emergency_contact_name }}" maxlength="190">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold" for="child-{{ $child->id }}-emergency-phone">{{ __('families.emergency_contact_phone_label') }}</label>
                                                <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="child-{{ $child->id }}-emergency-phone" name="emergency_contact_phone" type="tel" value="{{ $isChildFormContext ? old('emergency_contact_phone', $child->emergency_contact_phone_e164) : $child->emergency_contact_phone_e164 }}" maxlength="30" inputmode="tel" dir="ltr">
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="block text-sm font-semibold" for="child-{{ $child->id }}-safety">{{ __('families.safety_notes_label') }}</label>
                                                <textarea class="mt-2 min-h-24 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="child-{{ $child->id }}-safety" name="safety_notes" maxlength="1000">{{ $isChildFormContext ? old('safety_notes', $child->safety_notes_encrypted) : $child->safety_notes_encrypted }}</textarea>
                                            </div>
                                        </div>
                                        <div class="flex justify-end">
                                            <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('families.save_child') }}</button>
                                        </div>
                                    </form>
                                    </details>
                                    @endif

                                    @if ($canManageConsent && data_get($consentStatuses, $child->id.'.child_data') === 'granted')
                                        <form class="mt-4 border-t border-[var(--pn-border)] pt-4" method="POST" action="{{ route('families.children.consent.withdraw', [$guardian, $child]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="consent_type" value="child_data">
                                            <input type="hidden" name="expected_version" value="{{ $child->lock_version }}">
                                            <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-danger)] px-4 font-semibold text-[var(--pn-danger)] hover:bg-[var(--pn-danger-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('families.withdraw_child_data_consent') }}</button>
                                            <p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('families.withdraw_child_data_consent_help') }}</p>
                                        </form>
                                    @endif

                                    @if ($loop->first && $canManageConsent && data_get($consentStatuses, $child->id.'.marketing') === 'granted')
                                        <form class="mt-4" method="POST" action="{{ route('families.children.consent.withdraw', [$guardian, $child]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="consent_type" value="marketing">
                                            <input type="hidden" name="expected_version" value="{{ $child->lock_version }}">
                                            <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('families.withdraw_marketing_consent') }}</button>
                                        </form>
                                    @endif

                                    @if ($canManageRelationships)
                                        <section class="mt-4 border-t border-[var(--pn-border)] pt-4" aria-labelledby="relationships-{{ $child->id }}">
                                            <h4 class="font-bold" id="relationships-{{ $child->id }}">{{ __('families.relationships_heading') }}</h4>
                                            <ul class="mt-3 space-y-2">
                                                @foreach ($relationships->get($child->id, collect()) as $relationshipRow)
                                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] bg-[var(--pn-surface-subtle)] p-3 text-sm">
                                                        <span>
                                                            <strong>{{ $relationshipRow->full_name }}</strong>
                                                            <span class="ms-2 text-[var(--pn-ink-muted)]" dir="ltr">{{ \App\Support\PhoneNormalizer::mask($relationshipRow->phone_e164) }}</span>
                                                            <span class="ms-2">{{ $relationshipTypes[$relationshipRow->relationship_type] ?? $relationshipRow->relationship_type }}</span>
                                                        </span>
                                                        @if ($relationshipRow->is_active)
                                                            <form method="POST" action="{{ route('families.relationships.revoke', [$guardian, $child, $relationshipRow->guardian_id]) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="expected_version" value="{{ $child->lock_version }}">
                                                                <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-danger)] px-3 font-semibold text-[var(--pn-danger)] hover:bg-[var(--pn-danger-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('families.revoke_relationship') }}</button>
                                                            </form>
                                                        @else
                                                            <span class="font-semibold text-[var(--pn-ink-muted)]">{{ __('families.relationship_revoked_state') }}</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>

                                            <form class="mt-4 grid gap-3 sm:grid-cols-2" method="POST" action="{{ route('families.relationships.store', [$guardian, $child]) }}">
                                                @csrf
                                                <input type="hidden" name="expected_version" value="{{ $child->lock_version }}">
                                                <div class="sm:col-span-2">
                                                    <label class="block text-sm font-semibold" for="relationship-{{ $child->id }}-phone">{{ __('families.related_guardian_phone') }}</label>
                                                    <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="relationship-{{ $child->id }}-phone" name="guardian_phone" type="tel" inputmode="tel" dir="ltr" maxlength="30" required>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-semibold" for="relationship-{{ $child->id }}-type">{{ __('families.relationship_type_label') }}</label>
                                                    <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="relationship-{{ $child->id }}-type" name="relationship_type" required>
                                                        @foreach (__('families.all_relationship_types') as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-semibold" for="relationship-{{ $child->id }}-verification">{{ __('families.verification_method') }}</label>
                                                    <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="relationship-{{ $child->id }}-verification" name="verification_method" required>
                                                        <option value="registered_phone_last_four">{{ __('families.verification_phone') }}</option>
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-sm font-semibold" for="relationship-{{ $child->id }}-verification-value">{{ __('families.verification_value') }}</label>
                                                    <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="relationship-{{ $child->id }}-verification-value" name="verification_value" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required dir="ltr">
                                                </div>
                                                <label class="flex min-h-11 items-center gap-3 sm:col-span-2">
                                                    <input class="size-5 accent-[var(--pn-primary)]" name="can_check_out" type="checkbox" value="1">
                                                    <span class="font-semibold">{{ __('families.can_check_out') }}</span>
                                                </label>
                                                <button class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] sm:col-span-2" type="submit">{{ __('families.save_relationship') }}</button>
                                            </form>
                                        </section>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            @if ($canCreateChild)
            <aside class="lg:sticky lg:top-24" aria-labelledby="add-child-heading">
                <section class="rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-primary-soft)] p-5 sm:p-6">
                    <h2 class="text-lg font-bold" id="add-child-heading">{{ __('families.add_child_heading') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]" id="add-child-help">{{ __('families.add_child_description') }}</p>

                    <details class="group mt-5" @if ($childCreateFormOpen) open @endif>
                        <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2"><span>{{ __('families.add_child') }}</span><span aria-hidden="true" class="ms-2 text-lg leading-none transition-transform group-open:rotate-45">+</span></summary>
                    <form class="mt-6 space-y-5" method="POST" action="{{ route('families.children.store', $guardian) }}" aria-describedby="{{ $errors->any() ? 'family-profile-errors add-child-help' : 'add-child-help' }}">
                        @csrf
                        <input type="hidden" name="form_context" value="child-create">
                        <input type="hidden" name="expected_version" value="{{ $guardian->lock_version }}">
                        <input type="hidden" name="notice_version" value="{{ $noticeVersion }}">
                        <div>
                            <label class="block text-sm font-semibold" for="new-child-name">{{ __('families.child_name_label') }}</label>
                            <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="new-child-name" name="child_name" type="text" value="{{ $oldFormContext === 'child-create' ? old('child_name') : '' }}" autocomplete="off" maxlength="190" required @if ($oldFormContext === 'child-create' && $errors->has('child_name')) aria-invalid="true" aria-describedby="new-child-name-error" @endif>
                            @if ($oldFormContext === 'child-create')
                                @error('child_name')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="new-child-name-error">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="new-child-dob">{{ __('families.date_of_birth_label') }}</label>
                            <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="new-child-dob" name="date_of_birth" type="date" value="{{ $oldFormContext === 'child-create' ? old('date_of_birth') : '' }}" autocomplete="bday" @if ($oldFormContext === 'child-create' && $errors->has('date_of_birth')) aria-invalid="true" aria-describedby="new-child-dob-error" @endif>
                            @if ($oldFormContext === 'child-create')
                                @error('date_of_birth')
                                    <p class="mt-1 text-sm text-[var(--pn-danger)]" id="new-child-dob-error">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="new-child-relationship">{{ __('families.relationship_type_label') }}</label>
                            <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="new-child-relationship" name="relationship_type" required aria-describedby="new-child-relationship-help{{ $errors->has('relationship_type') ? ' new-child-relationship-error' : '' }}" @error('relationship_type') aria-invalid="true" @enderror>
                                <option value="">{{ __('families.relationship_type_placeholder') }}</option>
                                @foreach ($relationshipTypes as $value => $label)
                                    <option value="{{ $value }}" @selected($oldFormContext === 'child-create' && old('relationship_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]" id="new-child-relationship-help">{{ __('families.relationship_type_help') }}</p>
                            @error('relationship_type')
                                <p class="mt-1 text-sm text-[var(--pn-danger)]" id="new-child-relationship-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="new-child-emergency-name">{{ __('families.emergency_contact_name_label') }}</label>
                            <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="new-child-emergency-name" name="emergency_contact_name" type="text" value="{{ $oldFormContext === 'child-create' ? old('emergency_contact_name') : '' }}" maxlength="190">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="new-child-emergency-phone">{{ __('families.emergency_contact_phone_label') }}</label>
                            <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="new-child-emergency-phone" name="emergency_contact_phone" type="tel" value="{{ $oldFormContext === 'child-create' ? old('emergency_contact_phone') : '' }}" maxlength="30" inputmode="tel" dir="ltr">
                            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('families.emergency_contact_help') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold" for="new-child-safety">{{ __('families.safety_notes_label') }}</label>
                            <textarea class="mt-2 min-h-24 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 py-2 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="new-child-safety" name="safety_notes" maxlength="1000">{{ $oldFormContext === 'child-create' ? old('safety_notes') : '' }}</textarea>
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3">
                            <input class="mt-1 size-5 shrink-0 accent-[var(--pn-primary)]" name="child_data_consent" type="checkbox" value="1" @checked($oldFormContext === 'child-create' && old('child_data_consent')) required>
                            <span>
                                <strong class="block">{{ __('families.child_data_consent_label') }}</strong>
                                <span class="mt-1 block text-sm text-[var(--pn-ink-muted)]">{{ __('families.child_data_consent_help') }}</span>
                            </span>
                        </label>
                        <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('families.add_child') }}</button>
                    </form>
                    </details>
                </section>
            </aside>
            @endif
        </div>
    </main>
@endsection
