<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class AutoLogoutAfterInactivity
{
    /**
     * Durée d'inactivité avant déconnexion (en minutes)
     * Par défaut : 30 minutes
     */
    protected int $timeout;

    public function __construct()
    {
        // Configurable via .env : SESSION_LIFETIME
        $this->timeout = config('session.lifetime', 30);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = Session::get('last_activity_time');
            $currentTime = now()->timestamp;

            // Si dernier activité existe
            if ($lastActivity) {
                $inactiveTime = $currentTime - $lastActivity;
                $timeoutSeconds = $this->timeout * 60;

                // Si le temps d'inactivité dépasse le timeout
                if ($inactiveTime > $timeoutSeconds) {
                    Auth::logout();
                    Session::flush();
                    Session::regenerate();

                    return redirect()->route('filament.admin.auth.login')
                        ->with('status', 'Vous avez été déconnecté pour inactivité.');
                }
            }

            // Mettre à jour le timestamp de la dernière activité
            Session::put('last_activity_time', $currentTime);
        }

        return $next($request);
    }
}
