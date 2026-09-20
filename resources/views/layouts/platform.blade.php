<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', __('platform.brand'))</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-[var(--pn-canvas)] text-[var(--pn-ink)] antialiased">
        <a class="sr-only z-50 rounded-[10px] bg-[var(--pn-surface)] px-4 py-3 font-semibold focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:ring-2 focus:ring-[var(--pn-focus)]" href="#main-content">{{ __('navigation.skip') }}</a>

        @auth
            <header class="border-b border-[var(--pn-border)] bg-[var(--pn-surface)]">
                <div class="mx-auto flex min-h-16 max-w-[1440px] flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6 lg:px-8">
                    <a class="flex min-h-11 items-center gap-3 rounded-[10px] px-1 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('platform.dashboard') }}">
                        <span class="grid size-9 place-items-center rounded-[10px] bg-[var(--pn-primary)] font-bold text-[var(--pn-surface)]" aria-hidden="true">P</span>
                        <span><strong class="block leading-none">{{ __('platform.brand') }}</strong><small class="mt-1 block text-xs text-[var(--pn-ink-muted)]">{{ __('platform.platform_label') }}</small></span>
                    </a>

                    <nav class="order-3 flex w-full flex-wrap gap-1 sm:order-none sm:w-auto sm:flex-1" aria-label="{{ __('platform.navigation.label') }}">
                        @foreach ([
                            ['platform.dashboard', 'platform_dashboard.navigation'],
                            ['platform.tenants.index', 'platform.navigation.tenants'],
                            ['platform.plans.index', 'platform.navigation.plans'],
                            ['platform.subscriptions.index', 'platform.navigation.subscriptions'],
                            ['platform.support-access.index', 'platform.navigation.support'],
                            ['platform.mfa.show', 'account_security.title'],
                        ] as [$route, $label])
                            @if (app('router')->has($route))
                                <a class="inline-flex min-h-11 shrink-0 items-center rounded-[10px] px-3 text-sm font-semibold {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-[var(--pn-primary-soft)] text-[var(--pn-primary)]' : 'text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)]' }} focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route($route) }}" @if (request()->routeIs(str_replace('.index', '.*', $route))) aria-current="page" @endif>{{ __($label) }}</a>
                            @endif
                        @endforeach
                    </nav>

                    <div class="ms-auto flex items-center gap-2">
                        <form method="POST" action="{{ route('locale.store') }}">
                            @csrf
                            <input type="hidden" name="locale" value="{{ app()->isLocale('ar') ? 'en' : 'ar' }}">
                            <button class="inline-flex min-h-11 items-center rounded-[10px] px-3 text-sm font-semibold text-[var(--pn-ink-muted)] hover:bg-[var(--pn-surface-subtle)] hover:text-[var(--pn-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ app()->isLocale('ar') ? __('platform.locale.english') : __('platform.locale.arabic') }}</button>
                        </form>
                        <form method="POST" action="{{ route('platform.logout') }}">
                            @csrf
                            <button class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('platform.tenants.sign_out') }}</button>
                        </form>
                    </div>
                </div>
            </header>
        @endauth

        <div id="main-content" tabindex="-1">
            @yield('content')
        </div>
    </body>
</html>
