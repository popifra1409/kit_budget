<?php
namespace App\Filament\Budget\Resources\DecisionPrevisionnelleResource\Pages;

use App\Filament\Budget\Resources\DecisionPrevisionnelleResource;
use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;

class ViewDecisionPrevisionnelle extends ViewRecord
{
    protected static string $resource = DecisionPrevisionnelleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->statut === 'brouillon'),

            // ── Valider ──────────────────────────────────────
            Actions\Action::make('valider')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->visible(fn() => $this->record->statut === 'brouillon')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->valider(auth()->user());
                    Notification::make()->title('✅ Validée')->success()->send();
                    $this->refreshFormData(['statut']);
                }),

            // ── Simuler engagement ───────────────────────────
            Actions\Action::make('engager')
                ->label('Simuler engagement')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->visible(fn() => in_array($this->record->statut, ['validee', 'valide']) && !$this->record->engagee)
                ->form([
                    Forms\Components\Select::make('nomenclature_id')
                        ->label('Nomenclature (simulation)')
                        ->options(
                            fn() =>
                            \App\Models\LigneBudgetaire::where('budget_id', $this->record->budget_id)
                                ->with('nomenclature')
                                ->get()
                                ->filter(fn($l) => $l->nomenclature)
                                ->mapWithKeys(fn($l) => [
                                    $l->nomenclature_id =>
                                        $l->nomenclature->code . ' - ' . $l->nomenclature->libelle .
                                        ' (Dispo: ' . number_format($l->disponible_engagement, 0, ',', ' ') . ' FCFA)'
                                ])
                        )
                        ->required()->searchable()
                        ->helperText('⚠️ Budget non impacté — simulation uniquement'),
                ])
                ->action(function (array $data) {
                    try {
                        $engagement = $this->record->engagerBudget($data['nomenclature_id']);
                        Notification::make()
                            ->title('🔮 Engagement simulé')
                            ->info()
                            ->body("N° {$engagement->numero} créé — budget non défalqué")
                            ->send();
                        $this->refreshFormData(['statut', 'engagee', 'numero_ce']);
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                    }
                }),

            // ── Convertir en DA réelle ───────────────────────
            Actions\Action::make('convertir')
                ->label('→ Convertir en DA réelle')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('success')
                ->visible(fn() => $this->record->statut !== 'annulee' && !$this->record->est_converti)
                ->requiresConfirmation()
                ->modalHeading('Convertir en Décision Administrative réelle')
                ->modalDescription('Une DA réelle sera créée. L\'engagement réel impactera le budget.')
                ->action(function () {
                    try {
                        $daReelle = $this->record->convertirEnDAReelle();
                        Notification::make()
                            ->title('✅ DA réelle créée')
                            ->success()
                            ->body("DA N° {$daReelle->numero} créée avec succès.")
                            ->send();
                        $this->redirect(
                            DecisionAdministrativeResource::getUrl('view', ['record' => $daReelle->id])
                        );
                    } catch (\Exception $e) {
                        Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                    }
                }),

            // ── Annuler ──────────────────────────────────────
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => !in_array($this->record->statut, ['annulee']))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->annuler();
                    Notification::make()->title('Annulée')->warning()->send();
                }),
        ];
    }
}