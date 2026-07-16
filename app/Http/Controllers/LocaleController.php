<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function setLocale(string $locale)
    {
        $supported = ['fr', 'en'];

        if (!in_array($locale, $supported)) {
            abort(400, 'Langue non supportée');
        }

        // Sauvegarder en DB si connecté
        if (auth()->check()) {
            auth()->user()->updateQuietly(['locale' => $locale]);
        }

        // Sauvegarder en session
        session(['locale' => $locale]);
        app()->setLocale($locale);

        return redirect()->back()
            ->with('success', "Langue changée : {$locale}");
    }
}
