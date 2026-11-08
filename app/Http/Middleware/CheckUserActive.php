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
                // Journalisé avant la déconnexion : après, il n'y a plus d'auteur à
                // l'entrée, et « un compte désactivé a tenté de travailler » se lit juste
                // à côté des échecs de connexion dans le même filtre du journal.
                \App\Models\ActivityLog::logAccesRefuse(
                    "session d'un compte désactivé",
                    $request->path(),
                    null,
                    ['motif' => 'compte inactif']
                );

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
