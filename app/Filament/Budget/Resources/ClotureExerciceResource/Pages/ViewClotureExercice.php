<?php

namespace App\Filament\Budget\Resources\ClotureExerciceResource\Pages;

use App\Filament\Budget\Resources\ClotureExerciceResource;
use App\Services\Budget\ClotureExerciceService;
use App\Services\ParametresExecution;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Circuit : recalcul → arrêté de l'ordonnateur → avis conforme du CA (→ reprise en N+1 : étape 5b). */
class ViewClotureExercice extends ViewRecord
{
    protected static string $resource = ClotureExerciceResource::class;

    protected function service(): ClotureExerciceService
    {
        return app(ClotureExerciceService::class);
    }

    protected function executer(callable $etape, string $succes): void
    {
        try {
            $etape();
            Notification::make()->success()->title($succes)->send();
            $this->redirect(ClotureExerciceResource::getUrl('view', ['record' => $this->getRecord()]));
        } catch (\DomainException $e) {
            Notification::make()->warning()->title('Action impossible')->body($e->getMessage())->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $r = fn() => $this->getRecord();
        $piece = fn(string $nom, string $label) => Forms\Components\FileUpload::make($nom)->label($label)
            ->disk('public')->directory('clotures')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']);

        return [
            Actions\Action::make('recalculer')
                ->label('Recalculer la situation')->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn() => $r()->statut === 'preparation' && auth()->user()?->can('gerer_cloture_exercice'))
                ->requiresConfirmation()
                ->modalDescription('Les montants sont recalculés à partir des engagements et des paiements. Les reports modifiés à la main sont conservés s\'ils restent valides.')
                ->action(fn() => $this->executer(fn() => $this->service()->calculer($r()), 'Situation recalculée')),

            Actions\Action::make('arreter')
                ->label("Arrêter les reports (ordonnateur)")->icon('heroicon-o-document-check')->color('warning')
                ->visible(fn() => $r()->statut === 'preparation' && auth()->user()?->can('arreter_reports_credits'))
                ->modalDescription(fn() => 'Reports retenus : ' . number_format((float) ($r()->totaux['report_retenu'] ?? 0), 0, ',', ' ')
                    . ' FCFA ; annulations : ' . number_format((float) ($r()->totaux['annule'] ?? 0), 0, ',', ' ') . ' FCFA. '
                    . (ParametresExecution::get('decideur_report') === 'ordonnateur_avis_ca' ? "L'avis conforme du CA sera ensuite requis." : ''))
                ->form([
                    Forms\Components\TextInput::make('reference_arrete')->label("Référence de l'arrêté")->required(),
                    Forms\Components\DatePicker::make('date_arrete')->label('Date')->default(now())->required(),
                    $piece('piece_arrete', 'Arrêté signé'),
                ])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->arreter($r(), $data), 'Arrêté de report enregistré')),

            Actions\Action::make('avis_ca')
                ->label('Avis du conseil d\'administration')->icon('heroicon-o-building-library')->color('info')
                ->visible(fn() => $r()->statut === 'arretee' && auth()->user()?->can('enregistrer_avis_ca'))
                ->form([
                    Forms\Components\Radio::make('avis_ca')->label('Avis')->options(['conforme' => 'Conforme', 'defavorable' => 'Défavorable'])->required()->live(),
                    Forms\Components\TextInput::make('reference_avis_ca')->label('Référence (délibération)')->required(),
                    Forms\Components\DatePicker::make('date_avis_ca')->label('Date')->default(now())->required(),
                    Forms\Components\Textarea::make('motif')->label('Motif (avis défavorable)')
                        ->visible(fn(Forms\Get $get) => $get('avis_ca') === 'defavorable')->required(fn(Forms\Get $get) => $get('avis_ca') === 'defavorable'),
                    $piece('piece_avis_ca', 'Délibération'),
                ])
                ->action(fn(array $data) => $this->executer(
                    fn() => $this->service()->enregistrerAvisCa($r(), $data),
                    $data['avis_ca'] === 'conforme' ? 'Avis conforme enregistré' : 'Avis défavorable : reports à revoir (retour en préparation)'
                )),

            Actions\Action::make('rouvrir')
                ->label('Retour en préparation')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                ->visible(fn() => $r()->statut === 'arretee' && auth()->user()?->can('arreter_reports_credits'))
                ->form([Forms\Components\Textarea::make('motif')->required()])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->rouvrirPreparation($r(), $data['motif']), 'Clôture remise en préparation')),
        ];
    }
}
