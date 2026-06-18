<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        if (!$event->user) return;

        activity()
            ->causedBy($event->user)
            ->event('logout')
            ->log('Déconnexion — ' . ($event->user->name ?? $event->user->email ?? 'Utilisateur'));
    }
}
