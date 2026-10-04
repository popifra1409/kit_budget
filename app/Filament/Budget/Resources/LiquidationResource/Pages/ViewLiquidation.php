<?php

namespace App\Filament\Budget\Resources\LiquidationResource\Pages;

use App\Filament\Budget\Resources\LiquidationResource;
use App\Services\Budget\LiquidationService;
use App\Services\ParametresExecution;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Fiche de liquidation : circuit service fait → liquidation → visa, et rejet motivé. */
class ViewLiquidation extends ViewRecord
{
    protected static string $resource = LiquidationResource::class;

    protected function service(): LiquidationService
    {
        return app(LiquidationService::class);
    }

    /** Exécute une étape du circuit avec un message clair en cas de règle non respectée. */
    protected function executer(callable $etape, string $succes): void
    {
        try {
            $etape();
            Notification::make()->success()->title($succes)->send();
            $this->refreshFormData(['statut']);
            $this->redirect(LiquidationResource::getUrl('view', ['record' => $this->getRecord()]));
        } catch (\DomainException $e) {
            Notification::make()->warning()->title('Action impossible')->body($e->getMessage())->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $r = fn() => $this->getRecord();

        return [
            // 1. Comptable matières
            Actions\Action::make('certifier')
                ->label('Certifier le service fait')
                ->icon('heroicon-o-check-badge')->color('warning')
                ->visible(fn() => $r()->statut === 'brouillon' && auth()->user()?->can('certifier_service_fait'))
                ->requiresConfirmation()
                ->modalDescription('Je certifie la réalité et la conformité du service fait, attestées par les preuves jointes.')
                ->action(fn() => $this->executer(fn() => $this->service()->certifierServiceFait($r()), 'Service fait certifié')),

            // 2. Ordonnateur
            Actions\Action::make('liquider')
                ->label('Liquider')
                ->icon('heroicon-o-scale')->color('info')
                ->visible(fn() => $r()->statut === 'service_fait_certifie' && auth()->user()?->can('liquider_depense'))
                ->modalHeading('Liquidation : arrêt du montant de la dette')
                ->form(fn() => [
                    Forms\Components\Placeholder::make('controles')
                        ->label('Contrôles')
                        ->content(fn() => new \Illuminate\Support\HtmlString(collect($this->service()->controler($r()))
                            ->map(fn($c) => ($c['ok'] ? '✅' : ($c['bloquant'] ? '❌' : '⚠️')) . ' ' . e($c['libelle']) . ' — <span style="color:#6b7280">' . e($c['detail']) . '</span>')
                            ->implode('<br>'))),
                    Forms\Components\DatePicker::make('date_liquidation')
                        ->label('Date de liquidation')->default(now())->required()->maxDate(now())
                        ->helperText(fn() => 'Échéance de paiement = date de liquidation + ' . ParametresExecution::get('delai_paiement_jours') . ' jours (paramètre en vigueur).'),
                ])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->liquider($r(), $data['date_liquidation']), 'Dépense liquidée')),

            // 3. Contrôleur financier
            Actions\Action::make('viser')
                ->label('Viser (contrôle de régularité)')
                ->icon('heroicon-o-shield-check')->color('success')
                ->visible(fn() => $r()->statut === 'liquidee' && auth()->user()?->can('viser_liquidation'))
                ->requiresConfirmation()
                ->action(fn() => $this->executer(fn() => $this->service()->viser($r()), 'Liquidation visée')),

            // Rejet motivé (retour en brouillon)
            Actions\Action::make('rejeter')
                ->label('Rejeter')
                ->icon('heroicon-o-arrow-uturn-left')->color('danger')
                ->visible(fn() => in_array($r()->statut, ['service_fait_certifie', 'liquidee', 'visee'], true)
                    && (auth()->user()?->can('liquider_depense') || auth()->user()?->can('viser_liquidation')))
                ->form([Forms\Components\Textarea::make('motif')->label('Motif du rejet')->required()->rows(3)])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->rejeter($r(), $data['motif']), 'Liquidation renvoyée en brouillon')),

            Actions\DeleteAction::make()->visible(fn() => LiquidationResource::canDelete($r())),
        ];
    }
}
