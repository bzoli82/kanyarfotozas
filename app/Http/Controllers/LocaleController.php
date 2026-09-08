<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, SetLocale::SUPPORTED, true)) {
            $request->session()->put('app_locale', $locale);
        }

        return back();
    }
}
