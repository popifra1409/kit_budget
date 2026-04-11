<?php

namespace App\Filament\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        // Après login → toujours rediriger vers le portail
        return redirect()->intended(route('filament.portal.pages.module-portal'));
    }
}
