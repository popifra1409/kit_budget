<?php

namespace App\Filament\Comptable\Widgets;

use Filament\Widgets\Widget;
use App\Models\FicheStock;

class MouvementsStockWidget extends Widget
{
    protected static ?int    $sort    = 5;
    protected static string  $view    = 'filament.comptable.widgets.mouvements-stock';
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        // Mouvements des 6 derniers mois
        $mois = [];
        for ($i = 5; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $label = $date->translatedFormat('M Y');

            $entrees = 0;
            $sorties = 0;

            if (class_exists(FicheStock::class)) {
                $entrees = FicheStock::whereMonth('date_mouvement', $date->month)
                    ->whereYear('date_mouvement', $date->year)
                    ->where('type_mouvement', 'entree')
                    ->sum('quantite') ?? 0;

                $sorties = FicheStock::whereMonth('date_mouvement', $date->month)
                    ->whereYear('date_mouvement', $date->year)
                    ->where('type_mouvement', 'sortie')
                    ->sum('quantite') ?? 0;
            }

            $mois[] = [
                'label'   => $label,
                'entrees' => (int) $entrees,
                'sorties' => (int) $sorties,
            ];
        }

        // Résumé du mois courant
        $totalEntreesMois = collect($mois)->last()['entrees'];
        $totalSortiesMois = collect($mois)->last()['sorties'];

        return [
            'mois'              => $mois,
            'totalEntreesMois'  => $totalEntreesMois,
            'totalSortiesMois'  => $totalSortiesMois,
        ];
    }
}
