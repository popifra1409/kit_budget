<?php

namespace App\Http\Controllers;

use App\Models\BonCommandeRegie;
use App\Models\EtatConfig;
use Barryvdh\DomPDF\Facade\Pdf;

class BonCommandeRegiePdfController extends Controller
{
    protected function preparerDonnees(BonCommandeRegie $bcr): array
    {
        $bcr->load([
            'lignes.referenceMercuriale',
            'fournisseur',
            'regieAvance',
            'ligneRegieAvance.nomenclature',
        ]);

        // ✅ Adapter les lignes BCR — ajouter accessor 'reference'
        $bcr->lignes->each(function ($ligne) {
            $ligne->reference = $ligne->referenceMercuriale?->code_reference
                ?? $ligne->reference_personnalisee
                ?? '—';
        });

        // ✅ Charger la config entête pour BCR/BCM
        $etatConfig = EtatConfig::where('type_document', 'bon_commande_regie')
            ->where('actif', true)
            ->where('est_defaut', true)
            ->first()
            // Fallback : première config active si aucune par défaut
            ?? EtatConfig::where('type_document', 'bon_commande_regie')
            ->where('actif', true)
            ->first();

        $createur = $bcr->created_by
            ? \App\Models\User::find($bcr->created_by)
            : null;

        return [
            '_raw'                     => $bcr,
            '_etat_config'             => $etatConfig,    
            'service'                  => $bcr->regieAvance?->libelle ?? 'RÉGIE',
            'numero_bca'               => $bcr->numero,
            'date_impression'          => now()->format('d/m/Y à H:i'),
            'prestataire_nom'          => $bcr->fournisseur?->raison_sociale    ?? '—',
            'prestataire_adresse'      => $bcr->fournisseur?->adresse           ?? '—',
            'prestataire_tel'          => $bcr->fournisseur?->telephone         ?? '—',
            'prestataire_contribuable' => $bcr->fournisseur?->numero_contribuable ?? '—',
            'montant_lettres'          => \App\Helpers\NombreEnLettres::montantCFA(
                $bcr->montant_ttc ?? 0
            ),
        ];
    }

    public function apercu(BonCommandeRegie $bcr)
    {
        $donnees = $this->preparerDonnees($bcr);

        $pdf = Pdf::loadView(
            'pdf.templates.bon-commande',  
            ['donnees' => $donnees]
        )
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top',    '20mm')
            ->setOption('margin-bottom', '15mm')
            ->setOption('margin-left',   '15mm')
            ->setOption('margin-right',  '15mm');

        return $pdf->stream("BCR_{$bcr->numero}.pdf");
    }

    public function telecharger(BonCommandeRegie $bcr)
    {
        $donnees = $this->preparerDonnees($bcr);

        $pdf = Pdf::loadView(
            'pdf.templates.bon-commande', 
            ['donnees' => $donnees]
        )
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top',    '20mm')
            ->setOption('margin-bottom', '15mm')
            ->setOption('margin-left',   '15mm')
            ->setOption('margin-right',  '15mm');

        return $pdf->download("BCR_{$bcr->numero}.pdf");
    }
}
