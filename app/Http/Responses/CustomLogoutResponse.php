<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class CustomLogoutResponse implements LogoutResponseContract
{
    /**
     * Créer une réponse de déconnexion personnalisée avec nettoyage complet
     */
    public function toResponse($request): RedirectResponse
    {
        // Invalider complètement la session
        $request->session()->invalidate();

        // Régénérer le token CSRF
        $request->session()->regenerateToken();

        // Vider la session
        Session::flush();

        // Détruire tous les cookies de session
        $cookies = ['laravel_session', 'XSRF-TOKEN', 'remember_web'];

        $response = redirect()->to(
            filament()->getLoginUrl()
        )->with('status', 'Déconnexion sécurisée effectuée. Vos données de session ont été effacées.');

        // Supprimer les cookies
        foreach ($cookies as $cookieName) {
            $response->withCookie(cookie()->forget($cookieName));
        }

        return $response;
    }
}
