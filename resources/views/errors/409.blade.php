@extends('layouts.app')

@section('title', __('staff.conflict_title').' · PlayNexus')

@section('content')
    <main class="mx-auto max-w-2xl px-4 py-12">
        <h1 class="text-2xl font-bold">{{ __('staff.conflict_title') }}</h1>
        <p class="mt-4 text-[var(--pn-ink-muted)]" role="alert">{{ $exception->getMessage() ?: __('staff.conflict') }}</p>
        <a class="mt-6 inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('tenant.show') }}">{{ __('tenant.page_title') }}</a>
    </main>
@endsection
