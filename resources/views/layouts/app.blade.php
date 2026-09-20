<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'PlayNexus')</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-[var(--pn-canvas)] text-[var(--pn-ink)] antialiased">
        <a class="sr-only z-50 rounded-[10px] bg-[var(--pn-surface)] px-4 py-3 font-semibold focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:ring-2 focus:ring-[var(--pn-focus)]" href="#main-content">{{ __('navigation.skip') }}</a>
        @auth
            @unless (request()->routeIs('platform.*'))
                <div class="min-h-screen" data-pn-shell data-pn-sidebar-collapsed="false">
                    @include('partials.navigation')
                    <div class="min-h-screen pt-16 lg:ps-[248px]" data-pn-main>
                        <div class="sticky top-16 z-30 hidden border-b border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] px-4 py-3 text-[var(--pn-warning)]" role="status" data-pn-offline-banner>
                            <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-3">
                                <p class="text-sm"><strong>{{ __('navigation.offline_title') }}</strong> {{ __('navigation.offline_description') }}</p>
                                <button class="min-h-11 rounded-[10px] border border-current px-4 text-sm font-semibold" type="button" data-pn-offline-retry>{{ __('navigation.retry_connection') }}</button>
                            </div>
                        </div>
                        <div id="main-content" tabindex="-1">
                            @yield('content')
                        </div>
                    </div>
                </div>
            @else
                <div id="main-content" tabindex="-1">
                    @yield('content')
                </div>
            @endunless
        @else
            <div id="main-content" tabindex="-1">
                @yield('content')
            </div>
        @endauth
    </body>
</html>
