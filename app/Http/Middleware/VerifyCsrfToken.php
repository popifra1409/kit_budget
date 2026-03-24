<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        'portal/login',
        'budget/login',
        'comptable/login',
        'marches/login',
        'livewire/update',
        'livewire/upload-file',
        'livewire/preview-file/*',
    ];
}
