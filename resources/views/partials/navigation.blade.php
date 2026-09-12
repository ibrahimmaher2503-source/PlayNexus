@php
    $navigationUser = auth()->user();
    $navigationTenant = $navigationUser?->tenant;
    $isOwner = $navigationTenant !== null && $navigationUser->can('view', $navigationTenant);
    $navigationBranch = $navigationUser?->accessibleBranches()
        ->whereKey(session('branch_id'))
        ->first();
    $navigationSections = [
        [
            'label' => __('navigation.workspace'),
            'links' => [
                ['dashboard', ['dashboard'], __('navigation.dashboard'), 'branches'],
            ],
        ],
    ];
    if (app('router')->has('families.index') && $navigationUser->can('viewAny', \App\Models\Guardian::class)) {
        $navigationSections[0]['links'][] = ['families.index', ['families.*'], __('families.page_title'), 'families'];
    }
    if ($isOwner) {
        $navigationSections[] = [
            'label' => __('navigation.management'),
            'links' => [
                ['tenant.show', ['tenant.show'], __('tenant.navigation_label'), 'tenant'],
                ['tenant.settings.edit', ['tenant.settings.*'], __('tenant_settings.title'), 'settings'],
                ['staff.index', ['staff.*'], __('tenant.manage_staff'), 'staff'],
                ['assignments.index', ['assignments.*'], __('tenant.manage_assignments'), 'permissions'],
                ['roles.index', ['roles.*'], __('roles.navigation_label'), 'roles'],
                ['branches.manage', ['branches.manage', 'branches.store', 'branches.status', 'branches.settings*'], __('tenant.manage_branches'), 'manage-branches'],
                ['audit.index', ['audit.*'], __('tenant.view_audit'), 'audit'],
            ],
        ];
    }
    $targetLocale = app()->isLocale('ar') ? 'en' : 'ar';
    $targetLanguage = $targetLocale === 'ar' ? __('Arabic') : __('English');
@endphp

<aside class="fixed inset-y-0 start-0 z-40 hidden w-[248px] flex-col border-e border-[var(--pn-border)] bg-[var(--pn-surface)] lg:flex">
    <a class="flex h-16 items-center gap-3 border-b border-[var(--pn-border)] px-5 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}">
        <span class="grid size-9 place-items-center rounded-[10px] bg-[var(--pn-primary)] font-bold text-[var(--pn-surface)]" aria-hidden="true">P</span>
        <span>
            <strong class="block leading-none text-[var(--pn-ink)]">PlayNexus</strong>
            <small class="mt-1 block text-xs text-[var(--pn-ink-muted)]">{{ __('navigation.venue_operations') }}</small>
        </span>
    </a>
    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-5" aria-label="{{ __('navigation.primary') }}">
        @include('partials.navigation-links', ['sections' => $navigationSections])
    </nav>
    <div class="border-t border-[var(--pn-border)] p-4">
        <p class="truncate text-sm font-semibold">{{ $navigationTenant?->name }}</p>
        <p class="mt-1 truncate text-xs text-[var(--pn-ink-muted)]">{{ __('navigation.tenant_context') }}</p>
    </div>
</aside>

<header class="fixed inset-x-0 top-0 z-30 h-16 border-b border-[var(--pn-border)] bg-[var(--pn-surface)] lg:start-[248px]">
    <div class="flex h-full items-center gap-3 px-4 sm:px-6">
        <details class="group lg:hidden">
            <summary class="pn-drawer-toggle grid size-11 cursor-pointer list-none place-items-center rounded-[10px] border border-[var(--pn-border)] hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" aria-label="{{ __('navigation.open_menu') }}">
                <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </summary>
            <div class="fixed inset-x-0 bottom-0 top-16 z-40 bg-[rgb(23_38_43_/_0.36)]">
                <div class="h-full w-[min(320px,88vw)] overflow-y-auto border-e border-[var(--pn-border)] bg-[var(--pn-surface)] p-4 shadow-[0_12px_32px_rgb(23_38_43_/_0.12)]">
                    <div class="mb-5 flex items-center gap-3 border-b border-[var(--pn-border)] pb-4">
                        <span class="grid size-9 place-items-center rounded-[10px] bg-[var(--pn-primary)] font-bold text-[var(--pn-surface)]" aria-hidden="true">P</span>
                        <div class="min-w-0">
                            <strong class="block">PlayNexus</strong>
                            <span class="block truncate text-xs text-[var(--pn-ink-muted)]">{{ $navigationTenant?->name }}</span>
                        </div>
                    </div>
                    <nav aria-label="{{ __('navigation.mobile') }}">
                        @include('partials.navigation-links', ['sections' => $navigationSections])
                    </nav>
                </div>
            </div>
        </details>

        <a class="font-bold text-[var(--pn-primary)] lg:hidden" href="{{ route('dashboard') }}">PlayNexus</a>

        <a class="ms-auto flex min-w-0 items-center gap-2 rounded-[10px] px-2 py-1.5 hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] sm:ms-0" href="{{ route('dashboard') }}">
            <span class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]" aria-hidden="true">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
            </span>
            <span class="hidden min-w-0 sm:block">
                <small class="block text-xs text-[var(--pn-ink-muted)]">{{ __('navigation.current_branch') }}</small>
                <strong class="block max-w-52 truncate text-sm">{{ $navigationBranch?->name ?? __('navigation.choose_branch') }}</strong>
            </span>
        </a>

        <div class="ms-auto flex items-center gap-2">
            <form method="POST" action="{{ route('locale.store') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $targetLocale }}">
                <button class="inline-flex min-h-11 items-center gap-2 rounded-[10px] px-3 text-sm font-semibold text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit" aria-label="{{ __('Switch to :language', ['language' => $targetLanguage]) }}">
                    <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                    <span class="hidden sm:inline">{{ $targetLanguage }}</span>
                </button>
            </form>

            <div class="hidden h-7 w-px bg-[var(--pn-border)] sm:block" aria-hidden="true"></div>

            <div class="hidden min-w-0 md:block">
                <p class="max-w-40 truncate text-sm font-semibold">{{ $navigationUser->name }}</p>
                <p class="max-w-40 truncate text-xs text-[var(--pn-ink-muted)]">{{ $isOwner ? __('navigation.tenant_owner') : __('navigation.staff_member') }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="grid size-11 place-items-center rounded-[10px] text-[var(--pn-ink-muted)] hover:bg-[var(--pn-danger-soft)] hover:text-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}">
                    <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17l5-5-5-5M15 12H3M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/></svg>
                </button>
            </form>
        </div>
    </div>
</header>
