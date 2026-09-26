<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            ActivityLog::logAuth('login', $event->user);
        }
    }
}
