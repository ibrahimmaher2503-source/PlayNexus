@php
    $navigationTenant = auth()->user()?->tenant;
    $isOwner = $navigationTenant !== null && auth()->user()->can('view', $navigationTenant);
    $links = [['dashboard', __('navigation.dashboard')]];
    if ($isOwner) {
        $links = array_merge($links, [
            ['tenant.show', __('tenant.navigation_label')],
            ['tenant.settings.edit', __('tenant_settings.title')],
            ['staff.index', __('tenant.manage_staff')],
            ['assignments.index', __('tenant.manage_assignments')],
            ['branches.manage', __('tenant.manage_branches')],
            ['audit.index', __('tenant.view_audit')],
        ]);
    }
@endphp

<div class="border-b border-[var(--pn-border)] bg-[var(--pn-surface)]">
    <nav class="mx-auto flex max-w-7xl items-center gap-2 overflow-x-auto px-4 py-3 sm:px-6 lg:px-8" aria-label="{{ __('navigation.primary') }}">
        <a class="me-2 shrink-0 font-bold text-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('dashboard') }}">PlayNexus</a>
        @foreach ($links as [$route, $label])
            <a class="inline-flex min-h-11 shrink-0 items-center rounded-[10px] px-3 text-sm font-semibold {{ request()->routeIs($route) ? 'bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]' : 'text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)]' }} focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</div>
