<?php

namespace App\Filament\Budget\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\PrevisionRecette;
use App\Models\RecetteReelle;
use App\Models\Exercice;
use App\Filament\Budget\Resources\RecetteReelleResource;
use App\Models\LignePrevisionRecette;
use App\Models\MouvementCollectif;
use App\Services\Budget\HistoriqueLigneBudgetaireService;
use App\Services\StatistiquesBudgetaires;

class StatsRecettesOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $exerciceActif = Exercice::getActif();

        if (!$exerciceActif) {
            return [];
        }

        // ✅ CORRIGÉ — la prévision ADOPTÉE en priorité (avant : la première trouvée, qui pouvait
        //    être une prévision clôturée)
        $prevision = PrevisionRecette::where('exercice_id', $exerciceActif->id)
            ->where('statut', '!=', 'elaboration')
            ->orderByRaw("CASE WHEN statut = 'adopte' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        if (!$prevision) {
            return [
                Stat::make('Aucune prévision active', 'Créez une prévision de recettes')
                    ->description('Exercice ' . $exerciceActif->annee)
                    ->descriptionIcon('heroicon-o-information-circle')
                    ->color('gray'),
            ];
        }

        $totalPrevu      = $prevision->getTotalPrevuRectifie();
        $totalRecouvre   = $prevision->getTotalRecouvre();
        $tauxGlobal      = $prevision->getTauxRecouvrement();
        $ecartGlobal     = $prevision->getEcartGlobal();
        $moisActuel      = now()->month;

        $recettesMoisActuel = RecetteReelle::where('exercice_id', $exerciceActif->id)
            ->where('mois', $moisActuel)
            ->whereIn('statut', ['encaissee', 'comptabilisee', 'validee'])
            ->sum('montant');

        $recettesEnAttente = RecetteReelle::where('exercice_id', $exerciceActif->id)
            ->where('statut', 'comptabilisee')
            ->count();

        // ── URL calculée en amont (Stat::url() n'accepte pas de Closure) ──
        $urlRecettes = null;
        try {
            $urlRecettes = RecetteReelleResource::getUrl('index', [
                'tableFilters' => ['mois' => ['value' => $moisActuel]]
            ]);
        } catch (\Exception $e) {
            //
        }

        $decomposition = $this->decomposition($prevision->id);
        $equilibre = $this->equilibre($decomposition['actualisees']);

        return [
            // ✅ AJOUT — prévisions actualisées = initiales + collectifs budgétaires (recettes)
            Stat::make('Prévisions de recettes', number_format($decomposition['actualisees'], 0, ',', ' ') . ' FCFA')
                ->description(
                    'Initiales ' . number_format($decomposition['initiales'], 0, ',', ' ')
                        . ' + collectifs ' . ($decomposition['collectifs'] >= 0 ? '+' : '') . number_format($decomposition['collectifs'], 0, ',', ' ')
                        . ($decomposition['coherent'] ? '' : ' — ⚠️ à recalculer (recettes:recalculer)')
                )
                ->descriptionIcon($decomposition['collectifs'] > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-document-text')
                ->color($decomposition['coherent'] ? 'primary' : 'warning'),

            // ✅ AJOUT — équilibre entre recettes et dépenses actualisées
            Stat::make('Équilibre budgétaire', $equilibre['ecart'] === null
                ? '—'
                : (abs($equilibre['ecart']) < 1 ? 'Équilibré' : (($equilibre['ecart'] > 0 ? '+' : '') . number_format($equilibre['ecart'], 0, ',', ' ') . ' FCFA')))
                ->description($equilibre['ecart'] === null
                    ? 'Aucun budget de dépenses actif'
                    : 'Recettes ' . number_format($decomposition['actualisees'], 0, ',', ' ')
                    . ' / Dépenses ' . number_format($equilibre['depenses'], 0, ',', ' '))
                ->descriptionIcon($equilibre['ecart'] !== null && abs($equilibre['ecart']) < 1 ? 'heroicon-o-scale' : 'heroicon-o-exclamation-triangle')
                ->color($equilibre['ecart'] === null ? 'gray' : (abs($equilibre['ecart']) < 1 ? 'success' : 'warning')),

            Stat::make('Total Recouvré', number_format($totalRecouvre, 0, ',', ' ') . ' FCFA')
                ->description('Prévu: ' . number_format($totalPrevu, 0, ',', ' ') . ' FCFA')
                ->descriptionIcon('heroicon-o-banknotes')
                ->chart($this->getRevenueChart($exerciceActif->id))
                ->color($tauxGlobal >= 90 ? 'success' : ($tauxGlobal >= 70 ? 'warning' : 'danger')),

            Stat::make('Taux de Réalisation', number_format($tauxGlobal, 1) . '%')
                ->description($this->getTauxDescription($tauxGlobal))
                ->descriptionIcon($this->getTauxIcon($tauxGlobal))
                ->color($tauxGlobal >= 90 ? 'success' : ($tauxGlobal >= 70 ? 'warning' : 'danger'))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Écart', ($ecartGlobal >= 0 ? '+' : '') . number_format($ecartGlobal, 0, ',', ' ') . ' FCFA')
                ->description($ecartGlobal >= 0 ? 'Surperformance' : 'Sous-performance')
                ->descriptionIcon($ecartGlobal >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($ecartGlobal >= 0 ? 'success' : 'danger'),

            Stat::make('Recettes ' . $this->getNomMois($moisActuel), number_format($recettesMoisActuel, 0, ',', ' ') . ' FCFA')
                ->description($recettesEnAttente . ' en attente de validation')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info')
                ->url($urlRecettes),
        ];
    }

    /**
     * ✅ AJOUT — Décomposition des prévisions de la prévision de recettes :
     *  - initiales   : prévisions votées (hors lignes créées par un collectif) ;
     *  - collectifs  : mouvements de recettes des collectifs adoptés non annulés
     *                  (augmentation de lignes existantes + lignes créées) ;
     *  - actualisees : somme des montants rectifiés des lignes.
     * « coherent » = actualisées égales à initiales + collectifs (sinon : php artisan recettes:recalculer).
     */
    protected function decomposition(int $previsionId): array
    {
        $lignes = LignePrevisionRecette::where('prevision_recette_id', $previsionId);
        $ligneIds = (clone $lignes)->pluck('id');

        $initiales = (float) (clone $lignes)
            ->where(fn($q) => $q->whereNull('est_issue_collectif')->orWhere('est_issue_collectif', false))
            ->sum('montant_prevu_initial');

        $collectifs = (float) MouvementCollectif::query()
            ->where('type', 'recette')
            ->where(fn($q) => $q->whereIn('ligne_recette_id', $ligneIds)->orWhereIn('nouvelle_ligne_recette_id', $ligneIds))
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->whereNull('date_annulation')
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
            ->sum('montant_modification');

        $actualisees = (float) (clone $lignes)->sum('montant_rectifie');

        return [
            'initiales'   => $initiales,
            'collectifs'  => $collectifs,
            'actualisees' => $actualisees,
            'coherent'    => abs($actualisees - ($initiales + $collectifs)) < 1,
        ];
    }

    /**
     * ✅ AJOUT — Équilibre : recettes actualisées − dépenses actualisées (budget actif,
     * même périmètre et mêmes règles que le widget du budget).
     */
    protected function equilibre(float $recettesActualisees): array
    {
        $vue = StatistiquesBudgetaires::getVueEnsemble();

        if (!isset($vue['budget'])) {
            return ['ecart' => null, 'depenses' => 0.0];
        }

        $depenses = app(HistoriqueLigneBudgetaireService::class)->syntheseBudgets([$vue['budget']->id])['budget_actualise'];

        return ['ecart' => $recettesActualisees - $depenses, 'depenses' => $depenses];
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
