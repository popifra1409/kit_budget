<?php

namespace App\Filament\Budget\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms;

class EditDecisionAdministrative extends EditRecord
{
    protected static string $resource = DecisionAdministrativeResource::class;

    /**
     * ✅ Actions dans l'en-tête - COMPLÉTÉES
     */
    protected function getHeaderActions(): array
    {
        return [
            // ✅ Voir
            Actions\ViewAction::make()
                ->label('Voir'),

            // ✅ Valider
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn() => $this->record->statut === 'brouillon')
                ->requiresConfirmation()
                ->modalHeading('Valider la décision')
                ->modalDescription(
                    fn() =>
                    "Valider la décision pour {$this->record->getNomCompletPersonnel()} d'un montant net de " .
                    number_format($this->record->montant_net, 0, ',', ' ') . " FCFA ?"
                )
                ->action(function () {
                    $this->record->valider(auth()->user());

                    Notification::make()
                        ->title('✅ Décision validée')
                        ->success()
                        ->body("La décision {$this->record->numero} a été validée avec succès.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            // ✅ Engager
            Actions\Action::make('engager')
                ->label('Engager le Budget')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn() => $this->record->statut === 'validee' && !$this->record->engagee)
                ->requiresConfirmation()
                ->modalHeading('Engager le budget')
                ->modalDescription(
                    fn() =>
                    "Engager le budget pour un montant de " .
                    number_format($this->record->montant_brut, 0, ',', ' ') . " FCFA ?"
                )
                ->form([
                    Forms\Components\Select::make('nomenclature_id')
                        ->label('Nomenclature budgétaire')
                        ->options(function () {
                            return \App\Models\LigneBudgetaire::where('budget_id', $this->record->budget_id)
                                ->with('nomenclature')
                                ->get()
                                ->filter(fn($lb) => $lb->nomenclature !== null)
                                ->mapWithKeys(function ($lb) {
                                    $code = $lb->nomenclature?->code ?? 'N/A';
                                    $libelle = $lb->nomenclature?->libelle ?? '';
                                    $dispo = number_format($lb->disponible_engagement, 0, ',', ' ');
                                    return [
                                        $lb->nomenclature_id => "{$code} - {$libelle} (Dispo: {$dispo} FCFA)"
                                    ];
                                });
                        })
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('Sélectionner la ligne budgétaire'),
                ])
                ->action(function (array $data) {
                    try {
                        $this->record->engagerBudget($data['nomenclature_id']);
                        $this->record->refresh();
                        $this->record->load('engagement');

                        Notification::make()
                            ->title('✅ Budget engagé avec succès')
                            ->success()
                            ->body("Engagement créé : " . ($this->record->engagement?->numero ?? 'N/A'))
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur lors de l\'engagement')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            // ✅ Annuler
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => !in_array($this->record->statut, ['annulee', 'payee']))
                ->requiresConfirmation()
                ->modalHeading('Annuler la décision')
                ->modalDescription('⚠️ Confirmer l\'annulation de cette décision ?')
                ->action(function () {
                    try {
                        $this->record->annuler();

                        Notification::make()
                            ->title('⚠️ Décision annulée')
                            ->warning()
                            ->body('La décision a été annulée avec succès.')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Impossible d\'annuler')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            // ✅ Supprimer (gardé de votre code original)
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->statut === 'brouillon'),
        ];
    }

    /**
     * ✅ Rediriger vers View après sauvegarde
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * ✅ VOTRE MÉTHODE EXISTANTE - Gardée telle quelle
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Mode forfait — neutraliser les taux
        if (($data['mode_saisie'] ?? 'calcule') === 'forfait') {
            $data['taux_tva'] = 0;
            $data['taux_cnps'] = 0;
            $data['taux_irnc'] = 0;
            $data['taux_redevance_audiovisuelle'] = 0;
            $data['taux_feicom'] = 0;
            $data['type_tva'] = 'forfait';
            return $data;
        }

        // Mode calculé — l'observer s'en charge
        return $data;
    }

    // protected static function calculerMontants(array $data): array
    // {
    //     $brut = (float) ($data['montant_brut'] ?? 0);
    //     $tauxCnps = (float) ($data['taux_cnps'] ?? 0);
    //     $tauxIrnc = (float) ($data['taux_irnc'] ?? 0);
    //     $autresRetenues = (float) ($data['autres_retenues'] ?? 0);

    //     $tauxTva = (float) ($data['taux_tva'] ?? 0);
    //     $montantTva = $data['type_tva'] === 'taux'
    //         ? round($brut / (1 + $tauxTva / 100) * ($tauxTva / 100), 2)
    //         : (float) ($data['montant_tva'] ?? 0);

    //     $montantHt = $data['type_tva'] === 'taux'
    //         ? round($brut / (1 + $tauxTva / 100), 2)
    //         : $brut - $montantTva;

    //     $montantCnps = round($montantHt * ($tauxCnps / 100), 2);
    //     $montantIrnc = round($montantHt * ($tauxIrnc / 100), 2);

    //     $data['montant_ht'] = $montantHt;
    //     $data['montant_tva'] = $montantTva;
    //     $data['montant_cnps'] = $montantCnps;
    //     $data['montant_irnc'] = $montantIrnc;
    //     $data['total_taxes'] = $montantCnps + $montantIrnc + $autresRetenues;
    //     $data['montant_net'] = $montantHt - $data['total_taxes'];

    //     return $data;
    // }
}
