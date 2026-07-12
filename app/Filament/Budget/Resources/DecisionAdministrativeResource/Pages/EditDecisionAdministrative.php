<?php

namespace App\Filament\Budget\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use App\Filament\Budget\Resources\DecisionAdministrativeResource\Concerns\GereCalculsMontants;
use App\Models\MemoireDepense;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms;

class EditDecisionAdministrative extends EditRecord
{
    use GereCalculsMontants;

    protected static string $resource = DecisionAdministrativeResource::class;

    // =========================================================
    // ✅ BLOCAGE GARANTI — niveau mount()
    //
    // Fonctionne même si l'utilisateur accède à l'URL /edit
    // directement depuis le navigateur.
    //
    // Si la DA est liée à un MD → redirect vers View + notification
    // =========================================================
    public function mount(int|string $record): void
    {
        parent::mount($record);

        // ✅ withoutGlobalScopes() — évite le filtre exercice
        $memoire = MemoireDepense::withoutGlobalScopes()
            ->where('decision_administrative_id', $this->record->id)
            ->first();

        if ($memoire) {
            Notification::make()
                ->title('🔒 Modification impossible')
                ->warning()
                ->body(
                    "Cette DA a été générée depuis le Mémoire {$memoire->numero}. "
                        . "Pour la modifier : annulez ou supprimez la DA — "
                        . "le mémoire sera automatiquement remis en Brouillon."
                )
                ->persistent()
                ->send();

            $this->redirect(
                DecisionAdministrativeResource::getUrl('view', [
                    'record' => $this->record->id,
                ])
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->label('Voir'),

            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn() => $this->record->statut === 'brouillon')
                ->requiresConfirmation()
                ->modalHeading('Valider la décision')
                ->modalDescription(
                    fn() =>
                    "Valider la décision pour {$this->record->getNomCompletPersonnel()} "
                        . "d'un montant net de "
                        . number_format($this->record->montant_net, 0, ',', ' ') . " FCFA ?"
                )
                ->action(function () {
                    $this->record->valider(auth()->user());
                    Notification::make()
                        ->title('✅ Décision validée')->success()
                        ->body("La décision {$this->record->numero} a été validée.")
                        ->send();
                    $this->redirect(
                        $this->getResource()::getUrl('view', ['record' => $this->record])
                    );
                }),

            Actions\Action::make('engager')
                ->label('Engager le Budget')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn() => $this->record->statut === 'validee' && !$this->record->engagee)
                ->requiresConfirmation()
                ->modalHeading('Engager le budget')
                ->modalDescription(
                    fn() =>
                    "Engager le budget pour un montant de "
                        . number_format($this->record->montant_brut, 0, ',', ' ') . " FCFA ?"
                )
                ->form([
                    Forms\Components\Select::make('nomenclature_id')
                        ->label('Nomenclature budgétaire')
                        ->options(function () {
                            return \App\Models\LigneBudgetaire::where('budget_id', $this->record->budget_id)
                                ->with('nomenclature')
                                ->get()
                                ->filter(fn($lb) => $lb->nomenclature !== null)
                                ->mapWithKeys(fn($lb) => [
                                    $lb->nomenclature_id =>
                                    "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} "
                                        . "(Dispo: " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                ]);
                        })
                        ->required()->searchable()->preload()
                        ->helperText('Sélectionner la ligne budgétaire'),
                ])
                ->action(function (array $data) {
                    try {
                        $this->record->engagerBudget($data['nomenclature_id']);
                        $this->record->refresh()->load('engagement');
                        Notification::make()
                            ->title('✅ Budget engagé avec succès')->success()
                            ->body("Engagement créé : " . ($this->record->engagement?->numero ?? 'N/A'))
                            ->send();
                        $this->redirect(
                            $this->getResource()::getUrl('view', ['record' => $this->record])
                        );
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('❌ Erreur lors de l\'engagement')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->peutEtreAnnulee())
                ->form([
                    Forms\Components\Textarea::make('motif')
                        ->label('Motif d\'annulation')->rows(3)->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    try {
                        $this->record->annuler($data['motif']);
                        Notification::make()->title('✅ Décision annulée')->success()->send();
                        $this->redirect(
                            $this->getResource()::getUrl('view', ['record' => $this->record])
                        );
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')->danger()
                            ->body($e->getMessage())->persistent()->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->statut === 'brouillon'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->preparerDonnees($data);
    }
}
