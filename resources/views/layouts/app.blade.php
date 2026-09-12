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
                <div class="min-h-screen">
                    @include('partials.navigation')
                    <div class="min-h-screen pt-16 lg:ps-[248px]">
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
