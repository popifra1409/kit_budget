<?php

namespace App\Observers;

use App\Models\BonCommande;

class BonCommandeObserver
{
    /**
     * Après création du BC
     */
    public function created(BonCommande $bonCommande): void
    {
        // Créer le dossier uniquement si le BC est validé
        if ($bonCommande->statut === 'valide') {
            $bonCommande->creerOuMettreAJourDossier();
        }
    }

    /**
     * Après mise à jour du BC
     */
    public function updated(BonCommande $bonCommande): void
    {
        // Si le BC vient d'être validé
        if ($bonCommande->isDirty('statut') && $bonCommande->statut === 'valide') {
            $dossier = $bonCommande->creerOuMettreAJourDossier();

            // Générer et attacher le PDF du BC
            try {
                $pdfPath = $this->genererEtSauvegarderPdf($bonCommande, 'bon_commande');

                $dossier->ajouterPiece([
                    'type_piece' => 'bon_commande',
                    'document_type' => get_class($bonCommande),
                    'document_id' => $bonCommande->id,
                    'nom_fichier' => "BC-{$bonCommande->numero}.pdf",
                    'chemin_fichier' => $pdfPath,
                    'type_mime' => 'application/pdf',
                    'taille' => \Storage::size($pdfPath),
                    'valide' => true,
                    'valide_par' => auth()->id(),
                    'date_validation' => now(),
                ]);
            } catch (\Exception $e) {
                \Log::error('Erreur génération PDF BC pour dossier : ' . $e->getMessage());
            }
        }

        // Si un engagement vient d'être créé
        if ($bonCommande->engagement && $bonCommande->isDirty('engagement_id')) {
            $dossier = $bonCommande->creerOuMettreAJourDossier();

            // Générer et attacher le PDF de l'engagement
            try {
                $engagement = $bonCommande->engagement;
                $pdfPath = $this->genererEtSauvegarderPdf($engagement, 'certificat_engagement');

                $dossier->ajouterPiece([
                    'type_piece' => 'engagement',
                    'document_type' => get_class($engagement),
                    'document_id' => $engagement->id,
                    'nom_fichier' => "ENG-{$engagement->numero}.pdf",
                    'chemin_fichier' => $pdfPath,
                    'type_mime' => 'application/pdf',
                    'taille' => \Storage::size($pdfPath),
                    'valide' => true,
                    'valide_par' => auth()->id(),
                    'date_validation' => now(),
                ]);

                // Mettre à jour le montant engagé
                $dossier->update([
                    'montant_engage' => $bonCommande->montant_ttc,
                    'statut' => 'en_cours',
                ]);
            } catch (\Exception $e) {
                \Log::error('Erreur génération PDF engagement pour dossier : ' . $e->getMessage());
            }
        }
    }

    /**
     * Générer et sauvegarder un PDF dans le dossier
     */
    protected function genererEtSauvegarderPdf($record, string $etatCode): string
    {
        $pdfGenerator = app(\App\Services\PdfGenerator\PdfGenerator::class);
        $pdfContent = $pdfGenerator->generer($etatCode, $record);

        // Sauvegarder dans storage
        $filename = "{$etatCode}-{$record->id}-" . time() . ".pdf";
        $path = "dossiers-fournisseurs/{$filename}";

        // ✅ CORRECTION : Utiliser put() avec le contenu du PDF
        \Storage::disk('public')->put($path, $pdfContent);

        return $path;
    }
}
