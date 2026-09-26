<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        // Utilisateur absent si la session a deja expire : rien a tracer
        if ($event->user instanceof User) {
            ActivityLog::logAuth('logout', $event->user);
        }
    }
}
