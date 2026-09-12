@extends('layouts.app')

@section('title', __('branches.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
            <div>
                <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}">{{ __('branches.back_to_dashboard') }}</a>
                <p class="mt-5 text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
                <h1 class="mt-1 text-2xl font-bold">{{ __('branches.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('branches.page_description') }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="min-h-11 rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('Sign out') }}</button>
            </form>
        </header>

        @if (session('success') || session('status_message'))
            <p class="mt-5 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] px-4 py-3 font-semibold text-[var(--pn-primary)]" role="status">{{ session('success') ?? session('status_message') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-surface)] px-4 py-3 text-[var(--pn-danger)]" role="alert" tabindex="-1">
                <p class="font-semibold">{{ __('branches.validation_failed') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5" aria-labelledby="create-branch-heading">
            <h2 class="text-lg font-bold" id="create-branch-heading">{{ __('branches.create_heading') }}</h2>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('branches.create_description') }}</p>
            <form class="mt-4 flex flex-wrap items-end gap-3" method="POST" action="{{ route('branches.store') }}">
                @csrf
                <div class="min-w-64 flex-1">
                    <label class="block text-sm font-semibold" for="branch-name">{{ __('branches.branch_name') }}</label>
                    <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="branch-name" name="name" value="{{ old('name') }}" minlength="2" maxlength="120" required>
                </div>
                <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('branches.create') }}</button>
            </form>
        </section>

        <section class="mt-8" aria-labelledby="branches-heading">
            <h2 class="text-lg font-bold" id="branches-heading">{{ __('branches.branches_heading') }}</h2>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('branches.branches_description') }}</p>

            @if ($branches->isEmpty())
                <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('branches.empty') }}</p>
            @else
                @php($reasons = __('branches.reasons'))
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]">
                    <table class="min-w-full text-start">
                        <caption class="sr-only">{{ __('branches.branches_heading') }}</caption>
                        <thead class="border-b border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] text-sm">
                            <tr>
                                <th class="px-4 py-3 font-semibold" scope="col">{{ __('branches.branch_name') }}</th>
                                <th class="px-4 py-3 font-semibold" scope="col">{{ __('branches.branch_status') }}</th>
                                <th class="px-4 py-3 font-semibold" scope="col">{{ __('branches.branch_action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--pn-border)]">
                            @foreach ($branches as $branch)
                                @php($formId = 'branch-status-'.$branch->id)
                                <tr class="align-top">
                                    <th class="px-4 py-4 text-start font-semibold" scope="row">{{ $branch->name }}</th>
                                    <td class="px-4 py-4">
                                        <span class="font-semibold {{ $branch->is_active ? 'text-[var(--pn-primary)]' : 'text-[var(--pn-ink-muted)]' }}">{{ $branch->is_active ? __('branches.active') : __('branches.inactive') }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a class="mb-2 inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('branches.settings', $branch) }}">{{ __('branch_settings.page_title') }}</a>
                                        <form id="{{ $formId }}" class="flex min-w-72 flex-wrap items-end gap-2" method="POST" action="{{ route('branches.status', $branch) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="expected_is_active" value="{{ $branch->is_active ? '1' : '0' }}">
                                            <div>
                                                <label class="block text-xs font-semibold" for="{{ $formId }}-status">{{ __('branches.new_status') }}</label>
                                                <select class="mt-1 min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $formId }}-status" name="is_active">
                                                    <option value="1" @selected($branch->is_active)>{{ __('branches.active') }}</option>
                                                    <option value="0" @selected(! $branch->is_active)>{{ __('branches.inactive') }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold" for="{{ $formId }}-reason">{{ __('branches.reason_code') }}</label>
                                                <select class="mt-1 min-h-11 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="{{ $formId }}-reason" name="reason_code" required>
                                                    <option value="">{{ __('branches.choose_reason') }}</option>
                                                    @foreach ($reasons as $reason => $label)
                                                        <option value="{{ $reason }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('branches.save_status') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
@endsection
