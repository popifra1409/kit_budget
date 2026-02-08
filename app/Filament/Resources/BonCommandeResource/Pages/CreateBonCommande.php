<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use App\Models\BonCommande;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateBonCommande extends CreateRecord
{
    protected static string $resource = BonCommandeResource::class;

    public ?int $draftId = null;

    /**
     * ===============================
     * INITIALISATION DU BROUILLON
     * ===============================
     */
    public function mount(): void
    {
        parent::mount();

        try {
            // Chercher un brouillon existant
            $draft = BonCommande::query()
                ->where('statut', 'brouillon')
                ->where('created_by', auth()->id())
                ->whereNull('numero')
                ->latest()
                ->first();

            // Créer un nouveau brouillon si aucun n'existe
            if (!$draft) {
                $exerciceActif = \App\Models\Exercice::getActif();
                $premierBudget = \App\Models\Budget::where('actif', true)->first();

                $draft = BonCommande::create([
                    'exercice_id' => $exerciceActif?->id,
                    'budget_id' => $premierBudget?->id,
                    'statut' => 'brouillon',
                    'created_by' => auth()->id(),
                    'date_emission' => now(),
                    'exonere_tva' => false,
                    'objet' => 'Brouillon',
                ]);
            }

            // ✅ Sauvegarder l'ID du brouillon
            $this->draftId = $draft->id;

            // Lier le formulaire au brouillon
            $this->form->model($draft)->fill();
        } catch (\Exception $e) {
            \Log::error("Erreur mount CreateBonCommande : " . $e->getMessage());

            Notification::make()
                ->title('❌ Erreur')
                ->danger()
                ->body('Impossible de charger le formulaire. Veuillez réessayer.')
                ->persistent()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    /**
     * ===============================
     * CRÉATION DU RECORD
     * ===============================
     */
    protected function handleRecordCreation(array $data): BonCommande
    {
        $draft = BonCommande::findOrFail($this->draftId);

        // 🔒 Forcer le statut brouillon
        $data['statut'] = 'brouillon';

        // Règle TVA
        $data = $this->forcerExonerationTVA($data);

        $draft->update($data);
        $draft->refresh();

        Notification::make()
            ->title('✅ Brouillon enregistré')
            ->success()
            ->body("Le bon de commande a été enregistré en brouillon.")
            ->send();

        return $draft;
    }


    /**
     * Redirection après création
     */
    protected function getRedirectUrl(): string
    {
        if (!$this->record || !$this->record->id) {
            return $this->getResource()::getUrl('index');
        }

        return $this->getResource()::getUrl('view', [
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

    /**
     * ===============================
     * NETTOYAGE : Supprimer les vieux brouillons
     * ===============================
     */
    public function __destruct()
    {
        try {
            BonCommande::where('statut', 'brouillon')
                ->whereNull('numero')
                ->where('created_at', '<', now()->subDay())
                ->delete();
        } catch (\Exception $e) {
            // Ignorer
        }
    }
}
