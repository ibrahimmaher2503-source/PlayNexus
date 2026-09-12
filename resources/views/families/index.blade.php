@extends('layouts.app')

@section('title', __('families.page_title').' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
            <div>
                <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
                <h1 class="mt-1 text-2xl font-bold">{{ __('families.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('families.page_description') }}</p>
            </div>
            <a class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" href="{{ route('families.create') }}">{{ __('families.add_link') }}</a>
        </header>

        @if (session('success'))
            <div class="mt-6 rounded-[14px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" id="families-errors" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('families.form_error') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:p-6" aria-labelledby="family-search-heading">
            <h2 class="text-lg font-bold" id="family-search-heading">{{ __('families.search_heading') }}</h2>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('families.search_description') }}</p>
            <form class="mt-5 flex flex-wrap items-end gap-3" method="GET" action="{{ route('families.index') }}">
                <div class="min-w-64 flex-1">
                    <label class="block text-sm font-semibold" for="family-search">{{ __('families.search_label') }}</label>
                    <input class="mt-1 min-h-12 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="family-search" name="q" type="search" value="{{ $search ?? '' }}" placeholder="{{ __('families.search_placeholder') }}" autocomplete="off" maxlength="100" autofocus>
                </div>
                <button class="inline-flex min-h-12 items-center rounded-[10px] bg-[var(--pn-primary)] px-5 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('families.search_submit') }}</button>
                @if (filled($search ?? null))
                    <a class="inline-flex min-h-12 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('families.index') }}">{{ __('families.clear_search') }}</a>
                @endif
            </form>
        </section>

        <section class="mt-8" aria-labelledby="families-results-heading">
            <h2 class="text-lg font-bold" id="families-results-heading">{{ __('families.results_heading') }}</h2>

            @if ($families->isEmpty())
                <div class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 shadow-sm" role="status">
                    <p class="font-semibold">{{ filled($search ?? null) ? __('families.no_matches_heading') : __('families.empty_heading') }}</p>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ filled($search ?? null) ? __('families.no_matches_description') : __('families.empty_description') }}</p>
                    @if (filled($search ?? null))
                        <a class="mt-4 inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('families.create', ['q' => $search]) }}">{{ __('families.add_link') }}</a>
                    @endif
                </div>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="families-results-heading" tabindex="0">
                    <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                        <caption class="sr-only">{{ __('families.results_table_caption') }}</caption>
                        <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                            <tr>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('families.guardian_name') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('families.guardian_phone') }}</th>
                                <th class="px-4 py-3 text-start" scope="col">{{ __('families.children') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($families as $guardian)
                                <tr class="align-top">
                                    <th class="px-4 py-4 text-start font-semibold" scope="row">{{ $guardian->full_name }}</th>
                                    <td class="px-4 py-4"><bdi dir="ltr">{{ $guardian->maskedPhone() }}</bdi></td>
                                    <td class="px-4 py-4">
                                        @if ($guardian->children->isEmpty())
                                            <span class="text-sm text-[var(--pn-ink-muted)]">{{ __('families.no_children') }}</span>
                                        @else
                                            <ul class="space-y-1">
                                                @foreach ($guardian->children as $child)
                                                    <li>{{ $child->full_name }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (method_exists($families, 'hasPages') && $families->hasPages())
                <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('families.pagination_label') }}">
                    <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('families.page_position', ['current' => $families->currentPage(), 'last' => $families->lastPage()]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($families->previousPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $families->previousPageUrl() }}" aria-label="{{ __('families.previous_page') }}">{{ __('families.previous') }}</a>
                        @endif
                        @if ($families->nextPageUrl())
                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ $families->nextPageUrl() }}" aria-label="{{ __('families.next_page') }}">{{ __('families.next') }}</a>
                        @endif
                    </div>
                </nav>
            @endif
        </section>
    </main>
@endsection
