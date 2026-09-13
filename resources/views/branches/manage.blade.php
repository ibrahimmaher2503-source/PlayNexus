@extends('layouts.app')

@section('title', __('branches.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-6xl px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <div>
                <h1 class="text-2xl font-bold">{{ __('branches.page_title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-[var(--pn-ink-muted)]">{{ __('branches.page_description') }}</p>
            </div>
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

        @php($createOpen = old('name') !== null || $errors->has('name'))
        <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5" id="create-branch" aria-labelledby="create-branch-heading">
            <details @if ($createOpen) open @endif>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 rounded-[10px] px-1 font-semibold text-[var(--pn-primary)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--pn-focus)]">
                    <span>
                        <span class="block text-lg font-bold text-[var(--pn-ink)]" id="create-branch-heading">{{ __('branches.create_heading') }}</span>
                        <span class="mt-1 block text-sm font-normal text-[var(--pn-ink-muted)]">{{ __('branches.create_description') }}</span>
                    </span>
                    <span aria-hidden="true" class="text-xl">+</span>
                </summary>
                <form class="mt-4 flex flex-wrap items-end gap-3 border-t border-[var(--pn-border)] pt-4" method="POST" action="{{ route('branches.store') }}" data-pn-form>
                    @csrf
                    <input type="hidden" name="creation_key" value="{{ old('creation_key', (string) Illuminate\Support\Str::uuid()) }}">
                    <div class="min-w-64 flex-1">
                        <label class="block text-sm font-semibold" for="branch-name">{{ __('branches.branch_name') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="branch-name" name="name" value="{{ old('name') }}" minlength="2" maxlength="120" required @error('name') aria-invalid="true" aria-describedby="branch-name-error" @enderror>
                        @error('name')<p class="mt-2 text-sm text-[var(--pn-danger)]" id="branch-name-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('branches.create') }}</button>
                </form>
            </details>
        </section>

        <section class="mt-8" aria-labelledby="branches-heading">
            <h2 class="text-lg font-bold" id="branches-heading">{{ __('branches.branches_heading') }}</h2>
            <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('branches.branches_description') }}</p>

            @if ($branches->isEmpty())
                <div class="mt-4 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                    <p class="text-[var(--pn-ink-muted)]">{{ __('branches.empty') }}</p>
                    <a class="mt-3 inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="#create-branch">{{ __('branches.create') }}</a>
                </div>
            @else
                <div class="mt-4 overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)]" data-pn-table data-pn-responsive-table>
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
                                <tr class="align-middle">
                                    <th class="px-4 py-4 text-start font-semibold" scope="row" data-label="{{ __('branches.branch_name') }}">{{ $branch->name }}</th>
                                    <td class="px-4 py-4" data-label="{{ __('branches.branch_status') }}">
                                        <span class="font-semibold {{ $branch->is_active ? 'text-[var(--pn-primary)]' : 'text-[var(--pn-ink-muted)]' }}">{{ $branch->is_active ? __('branches.active') : __('branches.inactive') }}</span>
                                    </td>
                                    <td class="px-4 py-4" data-label="{{ __('branches.branch_action') }}">
                                        <div class="flex flex-wrap items-center gap-2" data-pn-row-actions>
                                            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('branches.settings', $branch) }}">{{ __('branch_settings.page_title') }}</a>
                                            <details class="relative">
                                                <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 font-semibold outline-none hover:bg-[var(--pn-surface-subtle)] focus-visible:ring-2 focus-visible:ring-[var(--pn-focus)]">{{ __('branches.actions_menu') }}</summary>
                                                <div class="absolute end-0 top-full z-10 mt-2 min-w-64 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3 shadow-[0_12px_32px_rgb(23_38_43_/_0.12)]">
                                                    <p class="text-xs leading-5 text-[var(--pn-ink-muted)]" id="{{ $formId }}-consequence">{{ $branch->is_active ? __('branches.deactivate_help') : __('branches.reactivate_help') }}</p>
                                                    <form class="mt-3" id="{{ $formId }}" method="POST" action="{{ route('branches.status', $branch) }}" aria-describedby="{{ $formId }}-consequence" data-pn-form data-confirm="{{ $branch->is_active ? __('branches.deactivate_confirm') : __('branches.reactivate_confirm') }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="expected_is_active" value="{{ $branch->is_active ? '1' : '0' }}">
                                                        <input type="hidden" name="is_active" value="{{ $branch->is_active ? '0' : '1' }}">
                                                        <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] px-4 font-semibold focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] {{ $branch->is_active ? 'border border-[var(--pn-danger)] text-[var(--pn-danger)] hover:bg-[var(--pn-danger-soft)]' : 'bg-[var(--pn-primary)] text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)]' }}" type="submit">{{ $branch->is_active ? __('branches.deactivate') : __('branches.reactivate') }}</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </div>
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
