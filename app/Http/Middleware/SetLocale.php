<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        // Priorité 1 : locale de l'utilisateur connecté (DB)
        if (auth()->check()) {
            $locale = auth()->user()->locale;
        }

        // Priorité 2 : session
        $locale = $locale ?? session('locale');

        // Priorité 3 : navigateur (Accept-Language header)
        if (!$locale) {
            $browserLang = substr($request->getPreferredLanguage(['fr', 'en']), 0, 2);
            $locale = $browserLang;
        }

        // Priorité 4 : défaut config
        $locale = $locale ?? config('app.locale', 'fr');

        // Appliquer uniquement si langue supportée
        $supported = ['fr', 'en'];
        if (!in_array($locale, $supported)) {
            $locale = config('app.locale', 'fr');
        }

        app()->setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
