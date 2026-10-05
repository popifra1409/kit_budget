<?php

namespace App\Filament\Budget\Widgets;

use App\Filament\Budget\Resources\OrdonnancePaiementResource;
use App\Services\Budget\EcheancePaiementService;
use App\Services\ParametresExecution;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Délais de paiement après liquidation : arriérés, alertes, intérêts moratoires estimés.
 * Seuils et taux : paramètres d'exécution budgétaire.
 */
class EcheancesPaiementWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $s = app(EcheancePaiementService::class)->synthese();
        $f = fn(float $m) => number_format($m, 0, ',', ' ') . ' FCFA';

        $url = function (string $etat): ?string {
            try {
                return OrdonnancePaiementResource::getUrl('index', ['tableFilters' => ['echeance' => ['value' => $etat]]]);
            } catch (\Throwable) {
                return null;
            }
        };

        $delai = ParametresExecution::get('delai_paiement_jours');
        $taux = (float) ParametresExecution::get('taux_interets_moratoires');

        return [
            Stat::make('Arriérés de paiement', $s['arrieres_nombre'] . ' OP')
                ->description($s['arrieres_nombre']
                    ? $f($s['arrieres_montant']) . " au-delà de {$delai} jours"
                    : "Aucune dépense au-delà de {$delai} jours")
                ->descriptionIcon($s['arrieres_nombre'] ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($s['arrieres_nombre'] ? 'danger' : 'success')
                ->url($s['arrieres_nombre'] ? $url('arriere') : null),

            Stat::make('Échéances proches', $s['alertes_nombre'] . ' OP')
                ->description($s['alertes_nombre'] ? $f($s['alertes_montant']) . ' à payer avant échéance' : 'Aucune échéance proche')
                ->descriptionIcon('heroicon-m-clock')
                ->color($s['alertes_nombre'] ? 'warning' : 'gray')
                ->url($s['alertes_nombre'] ? $url('alerte_forte') : null),

            Stat::make('Intérêts moratoires estimés', $taux > 0 ? $f($s['interets_estimes']) : '—')
                ->description($taux > 0 ? "Au taux de {$taux} % par an sur les arriérés" : 'Taux non renseigné (paramètres d\'exécution)')
                ->descriptionIcon('heroicon-m-calculator')
                ->color($s['interets_estimes'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
