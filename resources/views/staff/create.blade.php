@extends('layouts.app')

@section('title', __('staff.add_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-3xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('staff.index') }}">{{ __('staff.back_to_staff') }}</a>
            <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('staff.add_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('staff.add_description') }}</p>
        </header>

        @if ($errors->any())
            <div class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="staff-add-errors" role="alert" aria-live="assertive">
                <p class="font-semibold">{{ __('staff.add_form_error') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8 max-w-xl rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="staff-add-form-heading">
            <h2 class="text-lg font-bold" id="staff-add-form-heading">{{ __('staff.add_form_caption') }}</h2>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('staff.add_note') }}</p>

            <form class="mt-6 space-y-5" method="POST" action="{{ route('staff.store') }}" aria-describedby="{{ $errors->any() ? 'staff-add-errors' : 'staff-add-form-heading' }}">
                @csrf
                <div>
                    <label class="block text-sm font-semibold" for="staff-name">{{ __('staff.add_name') }}</label>
                    <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="staff-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="staff-name-error" @enderror>
                    @error('name')
                        <p class="mt-1 text-sm text-[var(--pn-danger)]" id="staff-name-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold" for="staff-email">{{ __('staff.add_email') }}</label>
                    <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="staff-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required @error('email') aria-invalid="true" aria-describedby="staff-email-error" @enderror>
                    @error('email')
                        <p class="mt-1 text-sm text-[var(--pn-danger)]" id="staff-email-error">{{ $message }}</p>
                    @enderror
                </div>

                <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('staff.add_submit') }}</button>
            </form>
        </section>
    </main>
@endsection
