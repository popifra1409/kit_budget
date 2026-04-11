<?php

namespace App\Observers;

use App\Models\PieceDossier;

class PieceDossierObserver
{
    /**
     * Après création d'une pièce
     */
    public function created(PieceDossier $piece): void
    {
        $dossier = $piece->dossier;

        // Si c'est une facture définitive validée, mettre à jour le montant facturé
        if ($piece->type_piece === 'facture_definitive' && $piece->valide) {
            // Extraire le montant du document lié ou metadata
            $montantFacture = $piece->metadata['montant'] ?? $dossier->montant_total;

            $dossier->update([
                'montant_facture' => $montantFacture,
                'statut' => 'attente_paiement',
            ]);
        }

        // Si c'est un justificatif de paiement validé
        if ($piece->type_piece === 'justificatif_paiement' && $piece->valide) {
            $montantPaye = $piece->metadata['montant'] ?? $dossier->montant_facture;

            $dossier->update([
                'montant_paye' => $montantPaye,
            ]);

            // Si tout est payé, passer en attente validation pour clôture
            if ($dossier->montant_paye >= $dossier->montant_total) {
                $dossier->update(['statut' => 'attente_validation']);
            }
        }
    }

    /**
     * Après validation d'une pièce
     */
    public function updated(PieceDossier $piece): void
    {
        // Si la pièce vient d'être validée
        if ($piece->isDirty('valide') && $piece->valide) {
            $this->created($piece); // Réutiliser la logique de création
        }
    }
}
