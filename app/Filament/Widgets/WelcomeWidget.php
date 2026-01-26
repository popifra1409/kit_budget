<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class WelcomeWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-widget';

    protected int | string | array $columnSpan = 'full';

    /**
     * Visible pour tous les utilisateurs
     */
    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Données pour la vue
     */
    public function getData(): array
    {
        $user = auth()->user();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'permissions_count' => $user->getAllPermissions()->count(),
            'service' => $user->service?->nom ?? 'Non défini',
            // Ajouter des stats personnelles
            'engagements_count' => $user->engagements()->count(),
            'bordereaux_en_attente' => $user->bordereauxDetenus()->count(),
            'last_login' => $user->last_login_at?->diffForHumans(),
        ];
    }
}
