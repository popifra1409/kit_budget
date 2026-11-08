<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

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

        // La connexion précédente se lit dans le journal, seule source de l'historique :
        // last_login_at ne porte que la dernière, et une seconde colonne serait une
        // seconde vérité — avec une purge à 90 jours, la précédente peut avoir disparu du
        // journal, et l'écran affiche alors seulement la connexion en cours.
        $connexions = DB::table('activity_log')
            ->where('event', 'login')
            ->where('causer_type', get_class($user))
            ->where('causer_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(2)
            ->get(['created_at', 'ip_address']);

        $precedente = $connexions->get(1);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'permissions_count' => $user->getAllPermissions()->count(),
            'service' => $user->service?->nom ?? 'Non défini',
            // Ajouter des stats personnelles
            'engagements_count' => $user->engagements()->count(),
            'bordereaux_en_attente' => $user->bordereauxDetenus()->count(),
            'last_login' => $user->last_login_at?->format('d/m/Y \à H:i'),
            'previous_login' => $precedente
                ? Carbon::parse($precedente->created_at)->format('d/m/Y \à H:i')
                    .($precedente->ip_address ? ' depuis '.$precedente->ip_address : '')
                : null,
        ];
    }
}
