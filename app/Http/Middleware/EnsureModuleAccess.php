<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleId): mixed
    {
        // Laisser passer Livewire et AJAX
        if (
            $request->is('livewire/*')
            || $request->header('X-Livewire')
            || $request->wantsJson()
        ) {
            return $next($request);
        }

        $user = auth()->user();

        if (!$user) {
            return redirect()->to('/portal/login');
        }

        // Super admin et admin → accès total — on laisse Filament gérer ses propres erreurs
        if ($user->hasRole(['super_admin', 'admin'])) {
            return $next($request);
        }

        if (!$user->can("access_module_{$moduleId}")) {
            return redirect()->to('/portal')
                ->with(
                    'module_access_denied',
                    "Vous n'avez pas accès au module « {$moduleId} »."
                );
        }

        return $next($request);
    }
}
