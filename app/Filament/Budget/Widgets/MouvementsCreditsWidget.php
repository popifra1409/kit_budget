<?php

namespace App\Filament\Budget\Widgets;

use App\Models\VirementBudgetaire;
use App\Services\Budget\MouvementCreditService;
use App\Services\StatistiquesBudgetaires;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Suivi des mouvements de crédits du budget actif (« après ») :
 * cumul des virements face au plafond, fongibilités, mouvements en attente.
 */
class MouvementsCreditsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $budget = StatistiquesBudgetaires::getVueEnsemble()['budget'] ?? null;

        if (!$budget) {
            return [Stat::make('Mouvements de crédits', '—')->description('Aucun budget actif')];
        }

        $service = app(MouvementCreditService::class);
        $f = fn(float $m) => number_format($m, 0, ',', ' ') . ' FCFA';

        // Contrôle « à vide » (montant 0) : cumul actuel des virements face au plafond
        $plafond = $service->controlerPlafond($budget->id, 'virement', 0);
        $taux = $plafond['credits_ouverts'] > 0 ? round($plafond['cumul_avant'] / $plafond['credits_ouverts'] * 100, 2) : 0;
        $consomme = $plafond['plafond_pct'] > 0 ? $taux / $plafond['plafond_pct'] * 100 : 0;

        $gestion = VirementBudgetaire::withoutGlobalScope('exercice')
            ->where('budget_id', $budget->id)
            ->where(fn($q) => $q->whereNull('origine')->orWhere('origine', '!=', 'collectif'))
            ->with(['ligneSource', 'ligneDestination'])
            ->get();

        $fongibilites = $gestion->filter(fn($v) => in_array($v->statut, ['approuve', 'execute'], true) && $service->typeDe($v) === 'fongibilite');
        $enAttente = $gestion->where('statut', 'en_attente');

        return [
            Stat::make('Virements : cumul annuel', "{$taux} %")
                ->description($f($plafond['cumul_avant']) . " sur un plafond de {$plafond['plafond_pct']} % (" . $f($plafond['plafond_montant']) . ')')
                ->descriptionIcon('heroicon-m-scale')
                ->color($consomme >= 100 ? 'danger' : ($consomme >= 80 ? 'warning' : 'success')),

            Stat::make('Fongibilités', $fongibilites->count())
                ->description($f((float) $fongibilites->sum('montant')) . ' redéployés dans un même sous-programme')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('info'),

            Stat::make('Mouvements en attente', $enAttente->count())
                ->description($enAttente->count() ? $f((float) $enAttente->sum('montant')) . ' à décider' : 'Aucun mouvement en attente')
                ->descriptionIcon('heroicon-m-clock')
                ->color($enAttente->count() ? 'warning' : 'gray'),
        ];
    }
}
