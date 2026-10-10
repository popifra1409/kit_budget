<?php

namespace App\Filament\Budget\Widgets;

use App\Services\Budget\HistoriqueLigneBudgetaireService;
use App\Services\StatistiquesBudgetaires;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Vue d'ensemble du budget de l'exercice actif.
 *
 * Les quatre cartes s'additionnent : initial + collectifs + virements = actualisé.
 * ✅ CORRIGÉ — les collectifs budgétaires (augmentations / réductions) n'apparaissaient nulle part :
 *    l'écart entre budget initial et budget actualisé était inexpliqué. Les virements internes
 *    au budget ont un solde nul : on affiche donc aussi le montant réellement transféré.
 * ✅ CORRIGÉ — un collectif qui ne fait que transvaser des crédits (mouvements de type virement)
 *    laissait la carte à « 0 FCFA · Aucun collectif adopté », alors même qu'il était adopté.
 *
 * Mêmes règles que le budget rectifié des lignes et que l'historique du certificat d'engagement
 * (HistoriqueLigneBudgetaireService).
 */
class BudgetOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // Même périmètre que l'ancien widget : le budget ACTIF de l'exercice
        // (StatistiquesBudgetaires::getVueEnsemble()), pour des montants identiques.
        $vue = StatistiquesBudgetaires::getVueEnsemble();
        $budgetIds = isset($vue['budget']) ? [$vue['budget']->id] : [];

        $s = app(HistoriqueLigneBudgetaireService::class)->syntheseBudgets($budgetIds);
        $f = fn(float $montant) => StatistiquesBudgetaires::formatMontant($montant);

        // ── Collectifs budgétaires ────────────────────────────────
        // Un collectif qui ne fait que transvaser des crédits a un effet net nul sur
        // le budget : il doit malgré tout apparaître, avec ses virements.
        $detailCollectifs = $s['nb_collectifs'] > 0
            ? $s['nb_collectifs'] . ' collectif(s) adopté(s) · ' . $s['nb_augmentations'] . ' augmentation(s) · '
            . $s['nb_reductions'] . ' réduction(s)'
            . ($s['virements_collectif_nombre'] > 0
                ? ' · ' . $s['virements_collectif_nombre'] . ' virement(s) (' . $f($s['virements_collectif_montant']) . ' déplacés)'
                : '')
            : 'Aucun collectif adopté';

        $collectifActif = $s['nb_collectifs'] > 0;

        // « 0 FCFA » sans précision se lit comme une absence de collectif : ajouter la
        // mention de l'effet net quand le collectif ne fait que déplacer des crédits.
        $valeurCollectifs = ($s['collectifs_net'] > 0 ? '+' : '') . $f($s['collectifs_net'])
            . ($s['collectifs_net'] == 0.0 && $s['virements_collectif_nombre'] > 0 ? ' (effet net)' : '');

        // ── Virements ─────────────────────────────────────────────
        $detailVirements = $s['virements_nombre'] > 0
            ? $s['virements_nombre'] . ' virement(s) · solde net ' . $f($s['virements_net'])
            : 'Aucun virement exécuté';

        return [
            Stat::make('Budget initial', $f($s['budget_initial']))
                ->description('Exercice ' . ($vue['exercice'] ?? '—'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Collectifs budgétaires', $valeurCollectifs)
                ->description($detailCollectifs)
                ->descriptionIcon($s['collectifs_net'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($s['collectifs_net'] > 0 ? 'success' : ($s['collectifs_net'] < 0 ? 'danger' : ($collectifActif ? 'info' : 'gray'))),

            Stat::make('Virements', $f($s['virements_montant']) . ' transférés')
                ->description($detailVirements)
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color($s['virements_nombre'] > 0 ? 'info' : 'gray'),

            Stat::make('Budget actualisé', $f($s['budget_actualise']))
                ->description('Initial + collectifs + virements')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                ->chart([$s['budget_initial'], $s['budget_initial'] + $s['collectifs_net'], $s['budget_actualise']]),
        ];
    }
}
