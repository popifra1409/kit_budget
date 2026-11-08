<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if (!$event->user instanceof User) {
            return;
        }

        ActivityLog::logAuth('login', $event->user);

        // saveQuietly() : une connexion ne doit déclencher ni les événements du modèle, ni
        // une observation de User — le journal des écritures n'a rien à y voir.
        // withoutTimestamps() pare au plus pressé : `updated_at` se lit « dernier geste
        // d'administration » sur l'écran Utilisateurs ; le bousculer à chaque authentification
        // rendrait cette colonne illisible, et les rapports de comptes inactifs avec.
        User::withoutTimestamps(fn () => $event->user
            ->forceFill(['last_login_at' => now()])
            ->saveQuietly());
    }
}
