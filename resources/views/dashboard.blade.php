@extends('layouts.app')

@section('title', __('Branch context').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-5xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <div>
                <h1 class="text-2xl font-bold">{{ __('Branch context') }}</h1>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('Signed in as :user', ['user' => auth()->user()->name]) }}</p>
                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ $selectedBranch ? __('Current branch: :branch', ['branch' => $selectedBranch->name]) : __('Select an assigned branch to continue.') }}</p>
            </div>
        </header>

        <section class="mt-8 max-w-xl" aria-labelledby="branches-heading">
            <h2 class="text-lg font-bold" id="branches-heading">{{ __('Available branches') }}</h2>
            @if ($branches->isEmpty())
                <p class="mt-3 text-[var(--pn-ink-muted)]">{{ __('No active branches are available.') }}</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($branches as $branch)
                        <form method="POST" action="{{ route('branch-context.store', $branch) }}">
                            @csrf
                            <button class="flex min-h-14 w-full items-center justify-between rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-4 text-start hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">
                                <span class="font-semibold">{{ $branch->name }}</span>
                                <span class="text-sm text-[var(--pn-ink-muted)]">{{ $selectedBranch?->is($branch) ? __('Selected') : __('Select') }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
@endsection
