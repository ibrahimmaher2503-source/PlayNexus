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

        return redirect()->route($request->user() ? 'dashboard' : 'login');
    }
}
