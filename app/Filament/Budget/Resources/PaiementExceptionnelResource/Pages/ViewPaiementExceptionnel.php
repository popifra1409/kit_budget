<?php

namespace App\Filament\Budget\Resources\PaiementExceptionnelResource\Pages;

use App\Filament\Budget\Resources\PaiementExceptionnelResource;
use App\Services\Budget\PaiementExceptionnelService;
use App\Services\ParametresExecution;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Circuit : autoriser (ordonnateur) → enregistrer le paiement (agent comptable) → régulariser. */
class ViewPaiementExceptionnel extends ViewRecord
{
    protected static string $resource = PaiementExceptionnelResource::class;

    protected function service(): PaiementExceptionnelService
    {
        return app(PaiementExceptionnelService::class);
    }

    protected function executer(callable $etape, string $succes): void
    {
        try {
            $etape();
            Notification::make()->success()->title($succes)->send();
            $this->redirect(PaiementExceptionnelResource::getUrl('view', ['record' => $this->getRecord()]));
        } catch (\DomainException $e) {
            Notification::make()->warning()->title('Action impossible')->body($e->getMessage())->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $r = fn() => $this->getRecord();

        return [
            Actions\Action::make('autoriser')
                ->label('Autoriser')->icon('heroicon-o-shield-check')->color('info')
                ->visible(fn() => $r()->statut === 'brouillon' && auth()->user()?->can('autoriser_paiement_exceptionnel'))
                ->form([
                    Forms\Components\TextInput::make('reference_autorisation')->label('Référence de l\'acte d\'autorisation')->required(),
                    Forms\Components\DatePicker::make('date_autorisation')->label('Date de l\'acte')->default(now())->required(),
                    Forms\Components\FileUpload::make('piece_autorisation')->label('Acte signé')
                        ->disk('public')->directory('paiements-exceptionnels')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
                ])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->autoriser($r(), $data), 'Paiement exceptionnel autorisé')),

            Actions\Action::make('payer')
                ->label('Enregistrer le paiement')->icon('heroicon-o-banknotes')->color('warning')
                ->visible(fn() => $r()->statut === 'autorise' && auth()->user()?->can('payer_paiement_exceptionnel'))
                ->form([
                    Forms\Components\DatePicker::make('date_paiement')->default(now())->maxDate(now())->required()
                        ->helperText(fn() => 'À régulariser dans les ' . ParametresExecution::get('delai_regularisation_jours') . ' jours (paramètre en vigueur).'),
                    Forms\Components\Select::make('mode_paiement')->options(['virement' => 'Virement', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'autre' => 'Autre'])->required(),
                    Forms\Components\TextInput::make('reference_paiement')->label('Référence du paiement')->required(),
                    Forms\Components\FileUpload::make('piece_paiement')->label('Preuve du paiement')
                        ->disk('public')->directory('paiements-exceptionnels')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
                ])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->enregistrerPaiement($r(), $data), 'Paiement enregistré — régularisation à faire')),

            Actions\Action::make('regulariser')
                ->label('Régulariser')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn() => $r()->statut === 'paye' && auth()->user()?->can('regulariser_paiement_exceptionnel'))
                ->form(fn() => [
                    Forms\Components\Select::make('ordonnance_paiement_id')
                        ->label('OP de régularisation')
                        ->options($this->service()->opsRegularisables($r())->mapWithKeys(fn($op) => [$op->id => "{$op->numero} — " . number_format((float) $op->montant_brut, 0, ',', ' ') . ' FCFA']))
                        ->required()->searchable()
                        ->helperText('OP standard du même bénéficiaire, d\'un montant au moins égal, non déjà utilisée.'),
                    Forms\Components\Textarea::make('observations')->rows(2),
                ])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->regulariser($r(), (int) $data['ordonnance_paiement_id'], $data['observations'] ?? null), 'Paiement régularisé')),

            Actions\Action::make('annuler')
                ->label('Annuler')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn() => in_array($r()->statut, ['brouillon', 'autorise'], true) && auth()->user()?->can('autoriser_paiement_exceptionnel'))
                ->form([Forms\Components\Textarea::make('motif')->required()])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->annuler($r(), $data['motif']), 'Paiement exceptionnel annulé')),
        ];
    }
}
