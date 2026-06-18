<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

class ActivityLog extends SpatieActivity
{
    protected static function booted(): void
    {
        static::creating(function (self $activity) {
            if (app()->runningInConsole() && !app()->runningInConsole(false)) {
                return;
            }
            $activity->ip_address = $activity->ip_address ?? request()?->ip();
            $activity->user_agent = $activity->user_agent ?? request()?->userAgent();
        });
    }
}
