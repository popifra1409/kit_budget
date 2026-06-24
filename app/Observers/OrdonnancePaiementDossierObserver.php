<?php

namespace App\Observers;

use App\Models\OrdonnancePaiement;
use App\Services\DossierFournisseurService;

class OrdonnancePaiementDossierObserver
{
    /**
     * OP créée → ajouter au dossier fournisseur
     */
    public function created(OrdonnancePaiement $op): void
    {
        DossierFournisseurService::ajouterOPAuDossier($op);
    }

    /**
     * OP payée → mettre à jour montant_paye dans le dossier
     */
    public function updated(OrdonnancePaiement $op): void
    {
        if ($op->wasChanged('statut') && $op->statut === 'payee' && $op->type_ordonnance === 'standard') {
            $dossier = \App\Models\PieceDossier::where('document_type', get_class($op))
                ->where('document_id', $op->id)
                ->first()
                ?->dossierFournisseur;

            if ($dossier) {
                $totalPaye = \App\Models\OrdonnancePaiement::where('statut', 'payee')
                    ->where('type_ordonnance', 'standard')
                    ->whereHas('engagement', fn($q) => $q->where('engageable_type', $dossier->document_principal_type)
                        ->where('engageable_id', $dossier->document_principal_id))
                    ->sum('montant_net');

                $dossier->update(['montant_paye' => $totalPaye]);
            }
        }
    }

    /**
     * OP supprimée → retirer du dossier
     */
    public function deleted(OrdonnancePaiement $op): void
    {
        DossierFournisseurService::supprimerPieceParSource(get_class($op), $op->id);
    }

    public function forceDeleted(OrdonnancePaiement $op): void
    {
        DossierFournisseurService::supprimerPieceParSource(get_class($op), $op->id);
    }
}
