<?php

namespace App\Filament\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Resources\OrdonnancePaiementResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Engagement;

class CreateOrdonnancePaiement extends CreateRecord
{
    protected static string $resource = OrdonnancePaiementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Générer le numéro d'OP
        if (isset($data['engagement_id'])) {
            $engagement = Engagement::find($data['engagement_id']);
            if ($engagement) {
                $data['numero'] = \App\Models\OrdonnancePaiement::genererNumeroFromEngagement(
                    $engagement,
                    $data['type_ordonnance']
                );
            } else {
                $data['numero'] = \App\Models\OrdonnancePaiement::genererNumero(
                    $data['type_ordonnance']
                );
            }
        } else {
            $data['numero'] = \App\Models\OrdonnancePaiement::genererNumero(
                $data['type_ordonnance']
            );
        }

        // Définir le créateur
        $data['created_by'] = auth()->id();

        // Calculer mois et période depuis date_emission
        if (isset($data['date_emission'])) {
            $date = \Carbon\Carbon::parse($data['date_emission']);
            $data['mois_emission'] = $date->format('m');
            $data['periode'] = $date->format('m/Y');
        }

        // ✅ CRITIQUE : Définir le bénéficiaire pour les OP standard
        if (isset($data['engagement_id']) && $data['type_ordonnance'] === 'standard') {
            $engagement = Engagement::with('bonCommande.fournisseur')->find($data['engagement_id']);

            if ($engagement && $engagement->bonCommande && $engagement->bonCommande->fournisseur) {
                $fournisseur = $engagement->bonCommande->fournisseur;
                $data['beneficiaire_type'] = get_class($fournisseur);
                $data['beneficiaire_id'] = $fournisseur->id;

                // Log pour debug
                \Log::info('Bénéficiaire défini dans CreateOrdonnancePaiement', [
                    'beneficiaire_type' => $data['beneficiaire_type'],
                    'beneficiaire_id' => $data['beneficiaire_id'],
                    'fournisseur' => $fournisseur->raison_sociale ?? $fournisseur->name,
                ]);
            }
        }

        // Pour les OP impôt, pas de bénéficiaire (Direction des Impôts)
        if ($data['type_ordonnance'] === 'impot') {
            $data['beneficiaire_type'] = null;
            $data['beneficiaire_id'] = null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
