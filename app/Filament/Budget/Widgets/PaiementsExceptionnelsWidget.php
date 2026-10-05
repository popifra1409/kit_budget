<?php

namespace App\Filament\Budget\Widgets;

use App\Filament\Budget\Resources\PaiementExceptionnelResource;
use App\Services\Budget\PaiementExceptionnelService;
use App\Services\ParametresExecution;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Paiements sans ordonnancement préalable : régularisations à faire et en retard. */
class PaiementsExceptionnelsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_paiement_exceptionnel') ?? false;
    }

    protected function getStats(): array
    {
        $s = app(PaiementExceptionnelService::class)->synthese();
        $f = fn(float $m) => number_format($m, 0, ',', ' ') . ' FCFA';
        $url = fn() => rescue(fn() => PaiementExceptionnelResource::getUrl('index'), null, false);

        return [
            Stat::make('Paiements exceptionnels à régulariser', $s['a_regulariser_nombre'])
                ->description($s['a_regulariser_nombre'] ? $f($s['a_regulariser_montant']) . ' payés sans ordonnancement' : 'Aucun')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($s['a_regulariser_nombre'] ? 'warning' : 'success')
                ->url($s['a_regulariser_nombre'] ? $url() : null),

            Stat::make('Régularisations en retard', $s['en_retard_nombre'])
                ->description($s['en_retard_nombre']
                    ? $f($s['en_retard_montant']) . ' au-delà de ' . ParametresExecution::get('delai_regularisation_jours') . ' jours'
                    : 'Aucune régularisation en retard')
                ->descriptionIcon($s['en_retard_nombre'] ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($s['en_retard_nombre'] ? 'danger' : 'success'),

            Stat::make('Autorisés, non encore payés', $s['autorises_nombre'])
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
