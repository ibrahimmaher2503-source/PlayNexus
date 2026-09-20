@php
    $navigationUser = auth()->user();
    $navigationTenant = $navigationUser?->tenant;
    $isOwner = $navigationTenant !== null && $navigationUser->can('view', $navigationTenant);
    $navigationBranch = $navigationUser?->accessibleBranches()->whereKey(session('branch_id'))->first();
    $navigationRoles = \Illuminate\Support\Facades\DB::table('branch_user')
        ->where('tenant_id', $navigationUser->tenant_id)
        ->where('user_id', $navigationUser->id)
        ->where('is_active', true)
        ->pluck('role');
    $isBranchManager = $navigationRoles->contains('branch_manager');
    $hasOperationalRole = $navigationRoles->intersect(['branch_manager', 'reception_staff', 'reception', 'cashier'])->isNotEmpty();
    $canViewReports = app('router')->has('reports.index') && ($isOwner || $hasOperationalRole);
    $canViewNotifications = app('router')->has('notifications.index') && ($isOwner || $hasOperationalRole);

    $operationsLinks = [
        ['dashboard', ['dashboard'], __('navigation.dashboard'), 'branches'],
    ];
    if (app('router')->has('families.index') && $navigationUser->can('viewAny', \App\Models\Guardian::class)) {
        $operationsLinks[] = ['families.index', ['families.*'], __('families.page_title'), 'families'];
    }
    if (app('router')->has('sessions.index') && class_exists(\App\Models\PlaySession::class) && $navigationUser->can('viewAny', \App\Models\PlaySession::class)) {
        $operationsLinks[] = ['sessions.index', ['sessions.*'], __('sessions.page_title'), 'sessions'];
    }

    $salesLinks = [];
    if (app('router')->has('tickets.index') && $navigationUser->can('viewAny', \App\Models\Ticket::class)) {
        $salesLinks[] = ['tickets.index', ['tickets.*', 'ticket-types.*'], __('tickets.page_title'), 'tickets'];
    }
    if (app('router')->has('pos.index') && $navigationUser->can('viewAny', \App\Models\Product::class)) {
        $salesLinks[] = ['pos.index', ['pos.*'], __('pos.page_title'), 'pos'];
    }
    if (app('router')->has('transactions.index') && $navigationUser->can('viewAny', \App\Models\Product::class)) {
        $salesLinks[] = ['transactions.index', ['transactions.*', 'receipts.*'], __('transactions.page_title'), 'transactions'];
    }
    if (app('router')->has('pricing.index') && $navigationUser->can('viewAny', \App\Models\PricingRule::class)) {
        $salesLinks[] = ['pricing.index', ['pricing.*'], __('pricing.page_title'), 'pricing'];
    }

    $insightLinks = [];
    if ($canViewReports) {
        $insightLinks[] = [$isOwner || $isBranchManager ? 'reports.index' : 'reports.operations', ['reports.*'], __('reports.page_title'), 'reports'];
    }
    if ($canViewNotifications) {
        $insightLinks[] = ['notifications.index', ['notifications.*'], __('operational_notifications.page_title'), 'notifications'];
    }

    $navigationSections = [
        ['label' => __('navigation.operations'), 'links' => $operationsLinks],
        ['label' => __('navigation.sales'), 'links' => $salesLinks],
        ['label' => __('navigation.insights'), 'links' => $insightLinks],
    ];
    if ($isOwner) {
        $navigationSections[] = [
            'label' => __('navigation.management'),
            'links' => [
                ['tenant.settings.edit', ['tenant.settings.*', 'tenant.show'], __('navigation.organization'), 'tenant'],
                ['tenant.subscription.show', ['tenant.subscription.*'], __('subscription.page_title'), 'transactions'],
                ['staff.index', ['staff.*', 'assignments.*', 'roles.*'], __('navigation.staff_access'), 'staff'],
                ['branches.manage', ['branches.manage', 'branches.store', 'branches.status', 'branches.settings*'], __('tenant.manage_branches'), 'manage-branches'],
                ['families.privacy.index', ['families.privacy.*'], __('privacy.page_title'), 'privacy'],
                ['audit.index', ['audit.*'], __('tenant.view_audit'), 'audit'],
            ],
        ];
    } elseif ($isBranchManager) {
        $navigationSections[] = [
            'label' => __('navigation.management'),
            'links' => [
                ['staff.index', ['staff.*', 'assignments.*'], __('navigation.staff_access'), 'staff'],
                ['branches.manage', ['branches.manage', 'branches.status', 'branches.settings*'], __('tenant.manage_branches'), 'manage-branches'],
            ],
        ];
    }
    $navigationSections = array_values(array_filter($navigationSections, fn (array $section): bool => $section['links'] !== []));

    $branchAssignment = $navigationBranch
        ? $navigationUser->branches()->whereKey($navigationBranch->getKey())->first()?->pivot
        : null;
    $navigationRoleKey = $isOwner ? 'owner' : (string) ($branchAssignment?->role ?? 'staff');
    $navigationRoleLabel = __('dashboard.roles.'.$navigationRoleKey);
    if ($navigationRoleLabel === 'dashboard.roles.'.$navigationRoleKey) {
        $navigationRoleLabel = __('dashboard.roles.staff');
    }
    $navigationLocalTime = $navigationBranch ? now('UTC')->setTimezone($navigationBranch->timezone)->format('H:i') : null;
    $targetLocale = app()->isLocale('ar') ? 'en' : 'ar';
    $targetLanguage = $targetLocale === 'ar' ? __('Arabic') : __('English');
@endphp

<aside class="fixed inset-y-0 start-0 z-40 hidden w-[248px] flex-col overflow-hidden border-e border-[var(--pn-border)] bg-[var(--pn-surface)] lg:flex" data-pn-sidebar>
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-[var(--pn-border)] px-3">
        <a class="flex min-w-0 flex-1 items-center gap-3 rounded-[10px] p-2 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}" title="PlayNexus">
            <span class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-[var(--pn-primary)] font-bold text-[var(--pn-surface)]" aria-hidden="true">P</span>
            <span class="min-w-0" data-pn-sidebar-label><strong class="block truncate leading-none">PlayNexus</strong><small class="mt-1 block truncate text-xs text-[var(--pn-ink-muted)]">{{ __('navigation.venue_operations') }}</small></span>
        </a>
        <button class="grid size-9 shrink-0 place-items-center rounded-[9px] text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-sidebar-toggle data-expanded-label="{{ __('navigation.collapse_sidebar') }}" data-collapsed-label="{{ __('navigation.expand_sidebar') }}" aria-expanded="true" aria-label="{{ __('navigation.collapse_sidebar') }}" title="{{ __('navigation.collapse_sidebar') }}">
            <svg class="size-5 rtl:rotate-180" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" data-pn-sidebar-toggle-icon><path d="M4 5h16v14H4zM9 5v14M15 9l-3 3 3 3"/></svg>
        </button>
    </div>

    <a class="mx-3 mt-4 flex min-h-14 items-center gap-3 rounded-[12px] border border-[var(--pn-border)] bg-[var(--pn-surface-subtle)] p-3 hover:border-[var(--pn-border-strong)] hover:bg-[var(--pn-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}" title="{{ $navigationBranch?->name ?? __('navigation.choose_branch') }}">
        <span class="grid size-9 shrink-0 place-items-center rounded-[9px] bg-[var(--pn-surface)] text-[var(--pn-primary)]" aria-hidden="true"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></span>
        <span class="min-w-0" data-pn-sidebar-label><small class="block text-xs font-semibold text-[var(--pn-ink-muted)]">{{ __('navigation.current_branch') }}</small><strong class="mt-0.5 block truncate text-sm">{{ $navigationBranch?->name ?? __('navigation.choose_branch') }}</strong></span>
    </a>

    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-5" data-pn-sidebar-scroll aria-label="{{ __('navigation.primary') }}">
        @include('partials.navigation-links', ['sections' => $navigationSections, 'idPrefix' => 'desktop-nav'])
    </nav>

    <div class="shrink-0 border-t border-[var(--pn-border)] p-3" data-pn-sidebar-footer>
        <div class="flex items-center gap-3 rounded-[10px] p-2">
            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[var(--pn-primary-soft)] text-sm font-bold text-[var(--pn-primary)]" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($navigationUser->name, 0, 1)) }}</span>
            <span class="min-w-0" data-pn-sidebar-label><strong class="block truncate text-sm">{{ $navigationUser->name }}</strong><span class="mt-0.5 block truncate text-xs text-[var(--pn-ink-muted)]">{{ $navigationRoleLabel }} · {{ $navigationTenant?->name }}</span></span>
        </div>
    </div>
</aside>

<header class="fixed inset-x-0 top-0 z-30 h-16 border-b border-[var(--pn-border)] bg-[color:oklch(0.989_0.004_205_/_0.96)] backdrop-blur lg:start-[248px]" data-pn-topbar>
    <div class="flex h-full items-center gap-2 px-4 sm:px-6">
        <div class="lg:hidden">
            <button class="grid size-11 place-items-center rounded-[10px] border border-[var(--pn-border)] hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-menu-open aria-controls="pn-mobile-menu" aria-label="{{ __('navigation.open_menu') }}"><svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <dialog class="fixed start-0 top-0 m-0 h-dvh max-h-none w-[min(320px,88vw)] max-w-none overflow-y-auto border-0 border-e border-[var(--pn-border)] bg-[var(--pn-surface)] p-0 text-[var(--pn-ink)] shadow-[0_12px_32px_rgb(23_38_43_/_0.12)] backdrop:bg-[rgb(23_38_43_/_0.48)]" id="pn-mobile-menu" data-pn-menu>
                <div class="sticky top-0 z-10 border-b border-[var(--pn-border)] bg-[var(--pn-surface)] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid size-9 place-items-center rounded-[10px] bg-[var(--pn-primary)] font-bold text-[var(--pn-surface)]" aria-hidden="true">P</span><div class="min-w-0"><strong class="block">PlayNexus</strong><span class="block truncate text-xs text-[var(--pn-ink-muted)]">{{ $navigationTenant?->name }}</span></div></div>
                        <button class="grid size-11 shrink-0 place-items-center rounded-[10px] border border-[var(--pn-border)] hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-pn-menu-close aria-label="{{ __('navigation.close_menu') }}"><svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                    </div>
                    <a class="mt-4 flex min-h-14 items-center gap-3 rounded-[12px] bg-[var(--pn-surface-subtle)] p-3" href="{{ route('dashboard') }}"><span class="grid size-9 shrink-0 place-items-center rounded-[9px] bg-[var(--pn-surface)] text-[var(--pn-primary)]" aria-hidden="true"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></span><span class="min-w-0"><small class="block text-xs text-[var(--pn-ink-muted)]">{{ __('navigation.current_branch') }}</small><strong class="block truncate text-sm">{{ $navigationBranch?->name ?? __('navigation.choose_branch') }}</strong></span></a>
                </div>
                <nav class="px-3 py-5" aria-label="{{ __('navigation.mobile') }}">@include('partials.navigation-links', ['sections' => $navigationSections, 'idPrefix' => 'mobile-nav'])</nav>
            </dialog>
        </div>

        <a class="font-bold text-[var(--pn-primary)] lg:hidden" href="{{ route('dashboard') }}">PlayNexus</a>
        <a class="hidden min-w-0 items-center gap-3 rounded-[10px] px-2 py-1.5 hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)] sm:flex lg:me-auto" href="{{ route('dashboard') }}">
            <span class="grid size-9 shrink-0 place-items-center rounded-[10px] bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]" aria-hidden="true"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></span>
            <span class="min-w-0"><small class="block text-xs text-[var(--pn-ink-muted)]">{{ __('navigation.current_branch') }}</small><strong class="block max-w-52 truncate text-sm">{{ $navigationBranch?->name ?? __('navigation.choose_branch') }}</strong></span>
            @if ($navigationBranch)
                <span class="hidden border-s border-[var(--pn-border)] ps-3 xl:block"><small class="block text-xs text-[var(--pn-ink-muted)]">{{ __('navigation.local_time') }}</small><time class="block text-sm font-bold tabular-nums" datetime="{{ now('UTC')->toIso8601String() }}" data-pn-branch-clock data-timezone="{{ $navigationBranch->timezone }}"><bdi dir="ltr">{{ $navigationLocalTime }}</bdi></time></span>
            @endif
        </a>

        <div class="ms-auto flex items-center gap-1 lg:ms-0">
            <a class="grid size-11 place-items-center rounded-[10px] text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('account.mfa.show') }}" aria-label="{{ __('account_security.title') }}" title="{{ __('account_security.title') }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3z"/></svg></a>
            @if ($canViewNotifications)
                <a class="grid size-11 place-items-center rounded-[10px] text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('notifications.index') }}" aria-label="{{ __('navigation.open_notifications') }}" title="{{ __('navigation.open_notifications') }}"><svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></a>
            @endif
            <form method="POST" action="{{ route('locale.store') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $targetLocale }}">
                <button class="inline-flex min-h-11 items-center gap-2 rounded-[10px] px-3 text-sm font-semibold text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit" aria-label="{{ __('Switch to :language', ['language' => $targetLanguage]) }}"><svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg><span class="hidden sm:inline">{{ $targetLanguage }}</span></button>
            </form>
            <div class="hidden h-7 w-px bg-[var(--pn-border)] sm:block" aria-hidden="true"></div>
            <div class="hidden min-w-0 md:block"><p class="max-w-40 truncate text-sm font-semibold">{{ $navigationUser->name }}</p><p class="max-w-40 truncate text-xs text-[var(--pn-ink-muted)]">{{ $navigationRoleLabel }}</p></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="grid size-11 place-items-center rounded-[10px] text-[var(--pn-ink-muted)] hover:bg-[var(--pn-danger-soft)] hover:text-[var(--pn-danger)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}"><svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17l5-5-5-5M15 12H3M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/></svg></button>
            </form>
        </div>
    </div>
</header>
