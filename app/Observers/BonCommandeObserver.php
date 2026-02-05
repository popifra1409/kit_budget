<?php

namespace App\Observers;

use App\Models\BonCommande;

class BonCommandeObserver
{
    /**
     * Handle the BonCommande "saving" event.
     * Appliquer l'exonération de TVA avant la sauvegarde
     */
    public function saving(BonCommande $bonCommande): void
    {
        // Si le BC est exonéré de TVA, forcer taux_tva des lignes à 0
        if ($bonCommande->exonere_tva && $bonCommande->exists) {
            // Charger les lignes si pas déjà chargées
            if (!$bonCommande->relationLoaded('lignes')) {
                $bonCommande->load('lignes');
            }

            foreach ($bonCommande->lignes as $ligne) {
                if ($ligne->taux_tva != 0 || $ligne->montant_tva != 0) {
                    $ligne->appliquerExonerationTVA();
                    $ligne->saveQuietly();
                }
            }
        }
    }

    /**
     * Handle the BonCommande "retrieved" event.
     * Recalculer si exonéré lors du chargement (mode VIEW)
     */
    public function retrieved(BonCommande $bonCommande): void
    {
        // Forcer le recalcul si exonéré de TVA
        if ($bonCommande->exonere_tva && $bonCommande->exists) {
            $bonCommande->load('lignes');
            $recalculer = false;

            // Vérifier si au moins une ligne a une TVA non nulle
            foreach ($bonCommande->lignes as $ligne) {
                if ($ligne->taux_tva != 0 || $ligne->montant_tva != 0) {
                    $recalculer = true;
                    break;
                }
            }

            // Recalculer uniquement si nécessaire
            if ($recalculer) {
                $bonCommande->recalculerTousLesMontants();
            }
        }
    }

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
        // ===== GESTION DE L'EXONÉRATION TVA =====
        // Si l'état d'exonération TVA vient de changer
        if ($bonCommande->isDirty('exonere_tva')) {
            // Recalculer tous les montants
            $bonCommande->recalculerTousLesMontants();
        }

        // ===== GESTION DU DOSSIER =====
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

        // ✅ Récupérer l'objet PDF
        $pdf = $pdfGenerator->generer($etatCode, $record);

        // ✅ Convertir en string binaire
        $pdfContent = $pdf->output();

        // Sauvegarder dans storage
        $filename = "{$etatCode}-{$record->id}-" . time() . ".pdf";
        $path = "dossiers-fournisseurs/{$filename}";

        \Storage::disk('public')->put($path, $pdfContent);

        return $path;
    }
}
