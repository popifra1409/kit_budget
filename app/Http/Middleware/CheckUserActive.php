<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier si l'utilisateur est authentifié
        if (auth()->check()) {
            $user = auth()->user();

            // Vérifier si le compte est inactif
            if (!$user->actif) {
                // Déconnecter l'utilisateur
                auth()->logout();

                // Invalider la session
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Rediriger vers la page de compte désactivé avec un message
                return redirect()->route('compte.desactive');
            }
        }

        return $next($request);
    }
}
