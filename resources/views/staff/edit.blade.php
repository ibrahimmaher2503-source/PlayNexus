@extends('layouts.app')

@section('title', __('staff.edit_identity').' · PlayNexus')

@section('content')
    <main class="mx-auto max-w-2xl px-4 py-8">
        <a class="inline-flex min-h-11 items-center underline" href="{{ route('staff.index') }}">{{ __('staff.back_to_staff') }}</a>
        <h1 class="mt-4 text-2xl font-bold">{{ __('staff.edit_identity') }}</h1>
        <p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('staff.identity_help') }}</p>
        @if ($errors->any())
            <div class="mt-4 rounded-[10px] border border-[var(--pn-danger)] p-3 text-[var(--pn-danger)]" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        <form class="mt-6 space-y-5" method="POST" action="{{ route('staff.identity', $member) }}" data-pn-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="expected_name" value="{{ $member->name }}">
            <input type="hidden" name="expected_email" value="{{ $member->email }}">
            @foreach (['name', 'email'] as $field)
                <div>
                    <label class="block text-sm font-semibold" for="staff-{{ $field }}">{{ __('staff.add_'.$field) }}</label>
                    <input id="staff-{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" required maxlength="255" value="{{ old($field, $member->$field) }}" class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" @error($field) aria-invalid="true" aria-describedby="staff-{{ $field }}-error" @enderror>
                    @error($field)<p id="staff-{{ $field }}-error" class="mt-1 text-sm text-[var(--pn-danger)]">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <button type="submit" class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)]">{{ __('staff.save') }}</button>
        </form>
    </main>
@endsection
