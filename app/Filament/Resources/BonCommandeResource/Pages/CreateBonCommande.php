<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use App\Models\BonCommande;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateBonCommande extends CreateRecord
{
    protected static string $resource = BonCommandeResource::class;

    protected ?BonCommande $draft = null;

    /**
     * ===============================
     * INITIALISATION DU BROUILLON
     * ===============================
     */
    public function mount(): void
    {
        parent::mount();

        // Chercher un brouillon existant
        $this->draft = BonCommande::query()
            ->where('statut', 'brouillon')
            ->where('created_by', auth()->id())
            ->whereNull('numero') // Brouillons sans numéro définitif
            ->latest()
            ->first();

        // Créer un nouveau brouillon si aucun n'existe
        if (!$this->draft) {
            // ✅ OBTENIR DES VALEURS PAR DÉFAUT
            $exerciceActif = \App\Models\Exercice::getActif();
            $premierBudget = \App\Models\Budget::where('actif', true)->first();

            $this->draft = BonCommande::create([
                'exercice_id' => $exerciceActif?->id,
                'budget_id' => $premierBudget?->id, // ✅ VALEUR PAR DÉFAUT
                'statut' => 'brouillon',
                'created_by' => auth()->id(),
                'date_emission' => now(),
                'exonere_tva' => false,
                'objet' => 'Brouillon', // ✅ VALEUR TEMPORAIRE
            ]);
        }

        // Lier le formulaire au brouillon
        $this->form->model($this->draft)->fill();
    }

    /**
     * =====================================
     * AUTO-SAUVEGARDE À CHAQUE MODIFICATION
     * =====================================
     */
    protected function getFormSchema(): array
    {
        return array_map(function ($component) {
            return $component->afterStateUpdated(function () {
                try {
                    $data = $this->form->getState();

                    // Appliquer la règle d'exonération TVA
                    $data = self::forcerExonerationTVA($data);

                    // Sauvegarder sans déclencher les événements
                    $this->draft->updateQuietly($data);
                } catch (\Exception $e) {
                    \Log::warning("Erreur auto-sauvegarde BC : " . $e->getMessage());
                }
            });
        }, parent::getFormSchema());
    }

    /**
     * ===============================
     * VALIDATION FINALE
     * ===============================
     */
    protected function handleRecordCreation(array $data): BonCommande
    {
        // Passer en statut 'valide'
        $data['statut'] = 'valide';

        // Appliquer la règle d'exonération TVA
        $data = self::forcerExonerationTVA($data);

        // Mettre à jour le brouillon
        $this->draft->update($data);

        // Notification de succès
        Notification::make()
            ->title('Bon de commande créé')
            ->success()
            ->body("Le bon de commande {$this->draft->numero} a été créé avec succès.")
            ->send();

        return $this->draft;
    }

    /**
     * Redirection après création
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', [
            'record' => $this->draft,
        ]);
    }

    /**
     * ===============================
     * RÈGLE MÉTIER : EXONÉRATION TVA
     * ===============================
     */
    protected static function forcerExonerationTVA(array $data): array
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
