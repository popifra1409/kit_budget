<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        activity()
            ->causedBy($event->user)
            ->event('login')
            ->log('Connexion réussie — ' . ($event->user->name ?? $event->user->email ?? 'Utilisateur'));
    }
}
