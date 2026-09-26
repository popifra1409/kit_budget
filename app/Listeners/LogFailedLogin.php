<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        try {
            activity('security')
                ->causedBy($event->user) // compte existant si le mot de passe etait faux, sinon null
                ->withProperties([
                    // Uniquement l'identifiant tente : JAMAIS le mot de passe
                    'identifiant' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 191),
                    'compte_existant' => $event->user !== null,
                    'ip' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ])
                ->event('login_failed')
                ->log('Échec de connexion' . (!empty($event->credentials['email']) ? " pour {$event->credentials['email']}" : ''));
        } catch (\Throwable $e) {
            \Log::warning('LogFailedLogin échoué : ' . $e->getMessage());
        }
    }
}
