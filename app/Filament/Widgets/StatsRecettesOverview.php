<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\PrevisionRecette;
use App\Models\RecetteReelle;
use App\Models\Exercice;

class StatsRecettesOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $exerciceActif = Exercice::getActif();

        if (!$exerciceActif) {
            return [];
        }

        // Récupérer la prévision active
        $prevision = PrevisionRecette::where('exercice_id', $exerciceActif->id)
            ->where('statut', '!=', 'elaboration')
            ->first();

        if (!$prevision) {
            return [
                Stat::make('Aucune prévision active', 'Créez une prévision de recettes')
                    ->description('Exercice ' . $exerciceActif->annee)
                    ->descriptionIcon('heroicon-o-information-circle')
                    ->color('gray'),
            ];
        }

        // Calculs
        $totalPrevu = $prevision->getTotalPrevuRectifie();
        $totalRecouvre = $prevision->getTotalRecouvre();
        $tauxGlobal = $prevision->getTauxRecouvrement();
        $ecartGlobal = $prevision->getEcartGlobal();

        // Recettes du mois en cours
        $moisActuel = now()->month;
        $recettesMoisActuel = RecetteReelle::where('exercice_id', $exerciceActif->id)
            ->where('mois', $moisActuel)
            ->whereIn('statut', ['encaissee', 'comptabilisee', 'validee'])
            ->sum('montant');

        // Nombre de recettes en attente de validation
        $recettesEnAttente = RecetteReelle::where('exercice_id', $exerciceActif->id)
            ->where('statut', 'comptabilisee')
            ->count();

        return [
            // Stat 1: Total Recouvré
            Stat::make('Total Recouvré', number_format($totalRecouvre, 0, ',', ' ') . ' FCFA')
                ->description('Prévu: ' . number_format($totalPrevu, 0, ',', ' ') . ' FCFA')
                ->descriptionIcon('heroicon-o-banknotes')
                ->chart($this->getRevenueChart($exerciceActif->id))
                ->color($tauxGlobal >= 90 ? 'success' : ($tauxGlobal >= 70 ? 'warning' : 'danger')),

            // Stat 2: Taux de Réalisation Global
            Stat::make('Taux de Réalisation', number_format($tauxGlobal, 1) . '%')
                ->description($this->getTauxDescription($tauxGlobal))
                ->descriptionIcon($this->getTauxIcon($tauxGlobal))
                ->color($tauxGlobal >= 90 ? 'success' : ($tauxGlobal >= 70 ? 'warning' : 'danger'))
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),

            // Stat 3: Écart
            Stat::make('Écart', ($ecartGlobal >= 0 ? '+' : '') . number_format($ecartGlobal, 0, ',', ' ') . ' FCFA')
                ->description($ecartGlobal >= 0 ? 'Surperformance' : 'Sous-performance')
                ->descriptionIcon($ecartGlobal >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($ecartGlobal >= 0 ? 'success' : 'danger'),

            // Stat 4: Mois Actuel
            Stat::make('Recettes ' . $this->getNomMois($moisActuel), number_format($recettesMoisActuel, 0, ',', ' ') . ' FCFA')
                ->description($recettesEnAttente . ' en attente de validation')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info')
                ->url(route('filament.admin.resources.recettes-reelles.index', [
                    'tableFilters' => ['mois' => ['value' => $moisActuel]]
                ])),
        ];
    }

    /**
     * Graphique d'évolution des recettes (7 derniers jours)
     */
    protected function getRevenueChart(int $exerciceId): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $montant = RecetteReelle::where('exercice_id', $exerciceId)
                ->whereDate('date_recette', $date)
                ->sum('montant');
            $data[] = $montant / 1000000; // En millions
        }
        return $data;
    }

    /**
     * Description selon le taux
     */
    protected function getTauxDescription(float $taux): string
    {
        if ($taux >= 100) {
            return 'Objectif dépassé !';
        } elseif ($taux >= 90) {
            return 'Excellent parcours';
        } elseif ($taux >= 70) {
            return 'À surveiller';
        } else {
            return 'Action requise';
        }
    }

    /**
     * Icône selon le taux
     */
    protected function getTauxIcon(float $taux): string
    {
        if ($taux >= 100) {
            return 'heroicon-o-trophy';
        } elseif ($taux >= 90) {
            return 'heroicon-o-check-circle';
        } elseif ($taux >= 70) {
            return 'heroicon-o-exclamation-triangle';
        } else {
            return 'heroicon-o-x-circle';
        }
    }

    /**
     * Nom du mois en français
     */
    protected function getNomMois(int $mois): string
    {
        $moisFr = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];
        return $moisFr[$mois] ?? '';
    }
}
