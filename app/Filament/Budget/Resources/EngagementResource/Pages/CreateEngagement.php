<?php

namespace App\Filament\Budget\Resources\EngagementResource\Pages;

use App\Filament\Budget\Resources\EngagementResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\LigneBudgetaire;
use App\Models\LigneEngagement;
use Filament\Notifications\Notification;

class CreateEngagement extends CreateRecord
{
    protected static string $resource = EngagementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Gérer le bénéficiaire polymorphique
        if (isset($data['type_beneficiaire'])) {
            if ($data['type_beneficiaire'] === 'fournisseur') {
                $data['beneficiaire_type'] = \App\Models\Fournisseur::class;
                $data['beneficiaire_id'] = $data['beneficiaire_fournisseur_id'] ?? null;
            } else {
                $data['beneficiaire_type'] = \App\Models\User::class;
                $data['beneficiaire_id'] = $data['beneficiaire_personnel_id'] ?? null;
            }

            // Retirer les champs temporaires
            unset($data['type_beneficiaire']);
            unset($data['beneficiaire_fournisseur_id']);
            unset($data['beneficiaire_personnel_id']);
        }

        // Valeurs par défaut
        $data['statut'] = 'provisoire';
        $data['exercice'] = $data['exercice'] ?? now()->year;

        return $data;
    }

    protected function afterCreate(): void
    {
        $engagement = $this->record;

        try {
            \DB::beginTransaction();

            // Vérifier le crédit disponible
            $ligneBudgetaire = LigneBudgetaire::where('budget_id', $engagement->budget_id)
                ->where('nomenclature_id', $engagement->nomenclature_principale_id)
                ->first();

            if (!$ligneBudgetaire) {
                throw new \Exception("Ligne budgétaire non trouvée");
            }

            if (!$ligneBudgetaire->peutEngager($engagement->montant_engage)) {
                $nomenclature = $ligneBudgetaire->nomenclature;
                $manque = $engagement->montant_engage - $ligneBudgetaire->disponible_engagement;

                throw new \Exception(
                    "❌ CRÉDIT INSUFFISANT\n\n" .
                        "Ligne budgétaire: {$nomenclature->code} - {$nomenclature->libelle}\n\n" .
                        "📊 DÉTAILS:\n" .
                        "• Provision totale: " . number_format($ligneBudgetaire->montant_vote, 0, ',', ' ') . " FCFA\n" .
                        "• Déjà engagé: " . number_format($ligneBudgetaire->engage, 0, ',', ' ') . " FCFA\n" .
                        "• Disponible: " . number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . " FCFA\n\n" .
                        "💰 ENGAGEMENT DEMANDÉ:\n" .
                        "• Type: {$engagement->type_engagement}\n" .
                        "• Montant à engager: " . number_format($engagement->montant_engage, 0, ',', ' ') . " FCFA\n" .
                        "• Manque: " . number_format($manque, 0, ',', ' ') . " FCFA\n\n" .
                        "✅ SOLUTIONS:\n" .
                        "1. Réduire le montant de l'engagement\n" .
                        "2. Demander un virement budgétaire vers cette ligne\n" .
                        "3. Utiliser une autre nomenclature budgétaire"
                );
            }

            // Créer la ligne d'engagement
            LigneEngagement::create([
                'engagement_id' => $engagement->id,
                'nomenclature_id' => $engagement->nomenclature_principale_id,
                'numero_ligne' => 1,
                'libelle' => $engagement->objet,
                'montant' => $engagement->montant_engage,
            ]);

            // Engager la ligne budgétaire
            $ligneBudgetaire->enregistrerEngagement($engagement->montant_engage);

            \DB::commit();

            Notification::make()
                ->title('Engagement créé avec succès')
                ->success()
                ->body(
                    "Montant engagé: " . number_format($engagement->montant_engage, 0, ',', ' ') . " FCFA\n" .
                        "Ligne budgétaire mise à jour"
                )
                ->send();
        } catch (\Exception $e) {
            \DB::rollBack();

            // Supprimer l'engagement créé
            $engagement->delete();

            Notification::make()
                ->title('Erreur lors de la création')
                ->danger()
                ->body($e->getMessage())
                ->send();

            // Rediriger vers la liste
            $this->redirect(EngagementResource::getUrl('index'));
        }
    }
}
