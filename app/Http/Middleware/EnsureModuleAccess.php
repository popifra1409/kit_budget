<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleId): mixed
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->to('/portal/login');
        }

        // Super admin et admin → accès total
        if ($user->hasRole(['super_admin', 'admin'])) {
            return $next($request);
        }

        // Vérifier la permission du module
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
