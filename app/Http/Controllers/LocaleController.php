<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'string', Rule::in(['en', 'ar'])],
        ])['locale'];

        $request->session()->put('locale', $locale);

        $previous = parse_url(url()->previous());
        $previousPath = is_array($previous) ? ($previous['path'] ?? null) : null;
        $isLocalPage = is_string($previousPath) && (
            $previousPath === route('login', absolute: false)
            || $previousPath === route('dashboard', absolute: false)
            || $previousPath === route('platform.dashboard', absolute: false)
            || str_starts_with($previousPath, '/app/')
            || str_starts_with($previousPath, '/platform/')
        );

        if ($isLocalPage) {
            $query = isset($previous['query']) && is_string($previous['query']) ? '?'.$previous['query'] : '';

            return redirect()->to($previousPath.$query);
        }

        if ($request->user()) {
            return redirect()->route($request->user()->tenant_id === null ? 'platform.dashboard' : 'dashboard');
        }

        return redirect()->route('login');
    }
}
