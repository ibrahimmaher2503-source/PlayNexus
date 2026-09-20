@extends('layouts.app')

@section('title', __('roles.page_title') . ' · PlayNexus')

@section('content')
    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6">
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('roles.page_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('roles.page_description') }}</p>
        </header>

        <nav class="mt-5 flex flex-wrap gap-2" aria-label="{{ __('roles.hub_label') }}">
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('staff.index') }}">{{ __('roles.employees_tab') }}</a>
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('assignments.index') }}">{{ __('roles.access_tab') }}</a>
            <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-primary)] bg-[var(--pn-primary-soft)] px-4 font-semibold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('roles.index') }}" aria-current="page">{{ __('roles.roles_tab') }}</a>
        </nav>

        @if (session('success'))
            <p class="mt-6 rounded-[14px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-4 font-semibold text-[var(--pn-success)]" role="status" aria-live="polite">{{ session('success') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-[14px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-4 text-[var(--pn-danger)]" role="alert" aria-live="assertive" tabindex="-1">
                <p class="font-semibold">{{ __('roles.validation_failed') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
            <section class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm" aria-labelledby="create-role-heading">
                <h2 class="text-lg font-bold" id="create-role-heading">{{ __('roles.create_heading') }}</h2>
                <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('roles.create_description') }}</p>

                <form class="mt-5 space-y-5" method="POST" action="{{ route('roles.store') }}">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold" for="role-name">{{ __('roles.name') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="role-name" name="name" type="text" value="{{ old('name') }}" minlength="2" maxlength="120" autocomplete="off" required>
                    </div>

                    <fieldset class="rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4">
                        <legend class="px-1 text-sm font-semibold">{{ __('roles.permission_groups_heading') }}</legend>
                        <div class="mt-2 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3">
                            <h3 class="font-semibold">{{ __('roles.permission_group_branch_access') }}</h3>
                            <p class="mt-1 text-sm leading-5 text-[var(--pn-ink-muted)]">{{ __('roles.permission_group_branch_access_description') }}</p>
                            <label class="mt-3 flex min-h-11 cursor-pointer items-center gap-3 font-semibold" for="create-branches-view">
                                <input class="size-5 accent-[var(--pn-primary)]" id="create-branches-view" name="permissions[]" type="checkbox" value="branches.view" @checked(in_array('branches.view', old('permissions', ['branches.view']), true))>
                                <span>{{ __('roles.permission_branches_view') }}</span>
                            </label>
                            <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('roles.permission_note') }}</p>
                        </div>
                        <p class="mt-3 rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-3 text-sm leading-5 text-[var(--pn-ink-muted)]" role="status" aria-live="polite">{{ __('roles.new_role_impact') }}</p>
                    </fieldset>

                    <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] focus:ring-offset-2" type="submit">{{ __('roles.create') }}</button>
                </form>
            </section>

            <section aria-labelledby="roles-heading">
                <div>
                    <h2 class="text-lg font-bold" id="roles-heading">{{ __('roles.list_heading') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('roles.list_description') }}</p>
                </div>

                @if ($roles->isEmpty())
                    <p class="mt-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 text-[var(--pn-ink-muted)]" role="status">{{ __('roles.empty') }}</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($roles as $role)
                            <article class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm" aria-labelledby="role-{{ $role->id }}-name">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-bold" id="role-{{ $role->id }}-name">{{ $role->name }}</h3>
                                        <p class="mt-1 text-xs text-[var(--pn-ink-muted)]"><span class="font-semibold">{{ __('roles.code') }}:</span> <bdi dir="ltr">{{ $role->code }}</bdi></p>
                                    </div>
                                    <span class="rounded-full bg-[var(--pn-success-soft)] px-3 py-1 text-xs font-semibold text-[var(--pn-success)]">{{ __('roles.custom_badge') }}</span>
                                </div>

                                <div class="mt-4 grid gap-2 sm:grid-cols-2" aria-label="{{ __('roles.impact_heading') }}">
                                    <p class="rounded-[10px] bg-[var(--pn-surface-subtle)] px-3 py-2 text-sm text-[var(--pn-ink-muted)]"><span class="font-semibold"><bdi dir="ltr">{{ (int) ($role->assigned_staff_count ?? 0) }}</bdi></span> {{ __('roles.impact_staff', ['count' => '']) }}</p>
                                    <p class="rounded-[10px] bg-[var(--pn-surface-subtle)] px-3 py-2 text-sm text-[var(--pn-ink-muted)]"><span class="font-semibold"><bdi dir="ltr">{{ (int) ($role->assigned_branch_count ?? 0) }}</bdi></span> {{ __('roles.impact_branches', ['count' => '']) }}</p>
                                </div>

                                <details class="mt-4 pn-action-details">
                                    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]">{{ __('roles.edit_role') }}</summary>
                                    <form class="mt-4 flex flex-wrap items-end gap-3" id="role-{{ $role->id }}-form" method="POST" action="{{ route('roles.update', $role) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input name="expected_lock_version" type="hidden" value="{{ $role->lock_version }}">
                                        <div class="min-w-52 flex-1">
                                            <label class="block text-sm font-semibold" for="role-{{ $role->id }}-input">{{ __('roles.name') }}</label>
                                            <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="role-{{ $role->id }}-input" name="name" type="text" value="{{ $role->name }}" minlength="2" maxlength="120" required>
                                        </div>
                                        <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('roles.save') }}</button>
                                    </form>

                                    <fieldset class="mt-4 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-4">
                                        <legend class="px-1 text-sm font-semibold">{{ __('roles.permission_groups_heading') }}</legend>
                                        <div class="mt-2 rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3">
                                            <h4 class="font-semibold">{{ __('roles.permission_group_branch_access') }}</h4>
                                            <p class="mt-1 text-sm leading-5 text-[var(--pn-ink-muted)]">{{ __('roles.permission_group_branch_access_description') }}</p>
                                            <label class="mt-3 flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold" for="role-{{ $role->id }}-branches-view">
                                                <input class="size-5 accent-[var(--pn-primary)]" id="role-{{ $role->id }}-branches-view" name="permissions[]" type="checkbox" value="branches.view" form="role-{{ $role->id }}-form" @checked($role->permissions->contains('permission', 'branches.view'))>
                                                <span>{{ __('roles.permission_branches_view') }}</span>
                                            </label>
                                            <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('roles.permission_note') }}</p>
                                        </div>
                                        <p class="mt-3 rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-3 text-sm leading-5 text-[var(--pn-ink-muted)]">{{ __('roles.permission_impact_note') }}</p>
                                    </fieldset>
                                </details>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </main>
@endsection
