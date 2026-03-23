<?php

namespace App\Filament\Budget\Resources\BonCommandeResource\Pages;

use App\Filament\Budget\Resources\BonCommandeResource;
use App\Models\BonCommande;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Filament\Actions;

class CreateBonCommande extends CreateRecord
{
    protected static string $resource = BonCommandeResource::class;

    // protected function getFormActions(): array
    // {
    //     return [
    //         // Bouton "Enregistrer le brouillon"
    //         Actions\Action::make('save_draft')
    //             ->label('Enregistrer brouillon')
    //             ->icon('heroicon-o-document-duplicate')
    //             ->color('gray')
    //             ->action(function () {
    //                 $data = $this->form->getState();

    //                 // Forcer le statut à brouillon
    //                 $data['statut'] = 'brouillon';

    //                 // Créer le BC
    //                 $record = static::getModel()::create($data);

    //                 Notification::make()
    //                     ->title('Brouillon enregistré')
    //                     ->success()
    //                     ->body("Le bon de commande brouillon a été enregistré avec succès.")
    //                     ->send();

    //                 // Rediriger vers l'édition
    //                 return redirect()->route('filament.budget.resources.bon-commandes.edit', $record);
    //             })
    //             ->keyBindings(['command+s', 'ctrl+s']),  // Raccourci clavier Ctrl+S

    //         // Boutons standard
    //         $this->getCreateFormAction(),
    //         $this->getCreateAnotherFormAction(),
    //         $this->getCancelFormAction(),
    //     ];
    // }

    /**
     * ===============================
     * PRÉPARER LES DONNÉES AVANT CRÉATION
     * ===============================
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Créer directement en brouillon
        $data['created_by'] = auth()->id();
        $data['statut'] = 'brouillon'; // ← Commence en brouillon
        $data['date_emission'] = $data['date_emission'] ?? now();
        $data['exonere_tva'] = $data['exonere_tva'] ?? false;
        $data['net_a_percevoir'] = $data['net_a_percevoir'] ?? 0;

        // Exercice actif si non défini
        if (empty($data['exercice_id'])) {
            $exerciceActif = \App\Models\Exercice::getActif();
            $data['exercice_id'] = $exerciceActif?->id;
        }

        // Budget actif si non défini
        if (empty($data['budget_id'])) {
            $premierBudget = \App\Models\Budget::where('actif', true)->first();
            $data['budget_id'] = $premierBudget?->id;
        }

        // Appliquer la règle d'exonération TVA
        $data = $this->forcerExonerationTVA($data);

        return $data;
    }

    /**
     * ===============================
     * APRÈS LA CRÉATION
     * ===============================
     */
    protected function afterCreate(): void
    {
        try {
            Notification::make()
                ->title('✅ Brouillon créé')
                ->success()
                ->body("Le brouillon {$this->record->numero} a été créé. Vous pouvez maintenant le valider.")
                ->send();

            \Log::info("BC brouillon {$this->record->numero} créé");
        } catch (\Exception $e) {
            \Log::error("Erreur après création BC : " . $e->getMessage());
        }
    }

    /**
     * Redirection après création
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', [
            'record' => $this->record,
        ]);
    }

    /**
     * ===============================
     * RÈGLE MÉTIER : EXONÉRATION TVA
     * ===============================
     */
    protected function forcerExonerationTVA(array $data): array
    {
        if (!empty($data['exonere_tva'])) {
            $data['montant_tva'] = 0;

            foreach ($data['lignes'] ?? [] as $index => &$ligne) {
                $ligne['taux_tva'] = 0;
                $ligne['montant_tva'] = 0;

                $montantHT = (float) ($ligne['montant_ht'] ?? 0);
                $montantIR = (float) ($ligne['montant_ir'] ?? 0);

                $ligne['montant_ttc'] = $montantHT;
                $ligne['net_a_payer'] = $montantHT - $montantIR;
            }
            unset($ligne);
        }

        return $data;
    }
}
