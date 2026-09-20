@extends('layouts.app')

@section('title', __('setup.title').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ __('setup.eyebrow') }}</p>
            <div class="mt-1 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ __('setup.title') }}</h1>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('setup.description') }}</p>
                </div>
                <p class="rounded-[10px] bg-[var(--pn-primary-soft)] px-3 py-2 text-sm font-semibold text-[var(--pn-primary)]" aria-label="{{ __('setup.required_progress', ['complete' => $requiredComplete, 'total' => $requiredTotal]) }}">{{ __('setup.required_progress', ['complete' => $requiredComplete, 'total' => $requiredTotal]) }}</p>
            </div>
        </header>

        <section class="mt-6" aria-labelledby="setup-steps-heading">
            <h2 class="sr-only" id="setup-steps-heading">{{ __('setup.steps_heading') }}</h2>
            <ol class="divide-y divide-[var(--pn-border)] rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]">
                @foreach ($steps as $step)
                    <li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="flex min-w-0 gap-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $step['complete'] ? 'bg-[var(--pn-success-soft)] text-[var(--pn-success)]' : 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]' }}" aria-hidden="true">{{ $step['complete'] ? '✓' : '•' }}</span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold">{{ __('setup.steps.'.$step['key'].'.title') }}</h3>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $step['complete'] ? 'bg-[var(--pn-success-soft)] text-[var(--pn-success)]' : 'bg-[var(--pn-surface-subtle)] text-[var(--pn-ink-muted)]' }}">{{ $step['complete'] ? __('setup.complete') : ($step['optional'] ? __('setup.preparation') : __('setup.action_needed')) }}</span>
                                </div>
                                <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('setup.steps.'.$step['key'].'.description', $step) }}</p>
                            </div>
                        </div>
                        <a class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold text-[var(--pn-primary)] hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" href="{{ route($step['route']) }}">{{ __('setup.steps.'.$step['key'].'.action') }}</a>
                    </li>
                @endforeach
            </ol>
        </section>

        <p class="mt-5 max-w-3xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('setup.note') }}</p>
    </main>
@endsection
