<?php

namespace App\Services;

use App\Models\DossierFournisseur;
use App\Models\PieceDossier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DossierFournisseurService
{
    // ════════════════════════════════════════════════════════
    // TYPES DE PIÈCES RECONNUS
    // ════════════════════════════════════════════════════════
    const TYPES = [
        'bon_commande'           => 'Bon de Commande',
        'decision_administrative' => 'Décision Administrative',
        'certificat_engagement'  => 'Certificat d\'Engagement',
        'ordonnance_paiement'    => 'Ordonnance de Paiement',
        'ordonnance_impot'       => 'Ordonnance de Paiement Impôt',
        'facture_proforma'       => 'Facture Proforma',
        'facture_definitive'     => 'Facture Définitive',
        'pv_reception'           => 'PV de Réception',
        'autre'                  => 'Autre document',
    ];

    // ════════════════════════════════════════════════════════
    // RECHERCHE DU DOSSIER D'UN DOCUMENT
    // ════════════════════════════════════════════════════════

    /**
     * ✅ AJOUT — Un document a-t-il fait l'objet d'un avenant de changement de bénéficiaire ?
     * Si oui, il peut avoir plusieurs dossiers (un par fournisseur successif).
     */
    public static function aChangeDeBeneficiaire($document): bool
    {
        if (!$document?->id) {
            return false;
        }

        return \App\Models\Avenant::query()
            ->where('type_correction', \App\Models\Avenant::TYPE_BENEFICIAIRE)
            ->where('statut', 'applique')
            ->whereIn('document_source_type', \App\Models\Engagement::formesDuType(get_class($document)))
            ->where('document_source_id', $document->id)
            ->exists();
    }

    /**
     * ✅ AJOUT — Dossier ACTUEL d'un document : celui de son fournisseur actuel.
     *
     * Avant les avenants de changement de bénéficiaire, un document n'avait qu'un dossier,
     * retrouvé par le seul document. Après un changement de fournisseur, l'ancien dossier
     * est conservé et un nouveau est ouvert : la recherche doit donc aussi porter sur le
     * fournisseur, sinon l'ancien dossier serait mis à jour à la place du nouveau.
     *
     * Repli historique (document sans changement de bénéficiaire) : premier dossier trouvé,
     * exactement comme avant. Aucun changement de comportement pour les dossiers existants.
     */
    public static function dossierDuDocument($document, ?int $fournisseurId = null): ?DossierFournisseur
    {
        if (!$document?->id) {
            return null;
        }

        $fournisseurId ??= $document->fournisseur_id ?? null;

        $base = DossierFournisseur::where('document_principal_type', get_class($document))
            ->where('document_principal_id', $document->id);

        if ($fournisseurId) {
            $dossier = (clone $base)->where('fournisseur_id', $fournisseurId)->first();
            if ($dossier) {
                return $dossier;
            }
        }

        // Document ayant changé de fournisseur : pas de repli sur le dossier d'un autre fournisseur
        if (static::aChangeDeBeneficiaire($document)) {
            return null;
        }

        return $base->first();
    }

    // ════════════════════════════════════════════════════════
    // OBTENIR OU CRÉER UN DOSSIER POUR UN FOURNISSEUR
    // ════════════════════════════════════════════════════════

    /**
     * Trouve le dossier lié à un document source ou en crée un nouveau.
     * Appelé automatiquement à l'engagement d'un BC ou d'une DA.
     */
    public static function obtenirOuCreer(
        int $fournisseurId,
        int $exerciceId,
        $documentPrincipal
    ): DossierFournisseur {

        $documentType = get_class($documentPrincipal);
        $documentId   = $documentPrincipal->id;

        // ✅ MODIFIÉ — dossier de ce document POUR CE fournisseur
        //    (après un avenant de changement de fournisseur, l'ancien dossier ne doit pas être repris)
        $dossier = static::dossierDuDocument($documentPrincipal, $fournisseurId);

        if ($dossier) {
            // Mettre à jour les montants
            $dossier->update([
                'montant_total'  => $documentPrincipal->montant_ttc
                    ?? $documentPrincipal->montant_brut
                    ?? $dossier->montant_total,
                'montant_engage' => $documentPrincipal->montant_ttc
                    ?? $documentPrincipal->montant_brut
                    ?? $dossier->montant_engage,
            ]);
            return $dossier;
        }

        // ✅ Créer un nouveau dossier
        $numero = DossierFournisseur::genererNumeroDossier($exerciceId);

        $typeDoc = match (true) {
            $documentPrincipal instanceof \App\Models\BonCommande            => 'bon_commande',
            $documentPrincipal instanceof \App\Models\DecisionAdministrative => 'decision_administrative',
            default                                                           => 'autre',
        };

        $dossier = DossierFournisseur::create([
            'numero_dossier'          => $numero,
            'fournisseur_id'          => $fournisseurId,
            'exercice_id'             => $exerciceId,
            'type_dossier'            => $typeDoc,
            'document_principal_type' => $documentType,
            'document_principal_id'   => $documentId,
            'reference_principale'    => $documentPrincipal->numero ?? '—',
            'objet'                   => $documentPrincipal->objet ?? "Dossier {$documentPrincipal->numero}",
            'montant_total'           => $documentPrincipal->montant_ttc ?? $documentPrincipal->montant_brut ?? 0,
            'montant_engage'          => $documentPrincipal->montant_ttc ?? $documentPrincipal->montant_brut ?? 0,
            'date_ouverture'          => now(),
            'date_limite_livraison'   => $documentPrincipal->date_livraison_prevue ?? null,
            'responsable_id'          => $documentPrincipal->created_by ?? auth()->id(),
            'createur_id'             => $documentPrincipal->created_by ?? auth()->id(),
            'statut'                  => 'ouvert',
        ]);

        Log::info("Dossier {$dossier->numero_dossier} créé automatiquement", [
            'fournisseur_id' => $fournisseurId,
            'document'       => $documentPrincipal->numero,
        ]);

        return $dossier;
    }

    // ════════════════════════════════════════════════════════
    // STRUCTURE DE LA TABLE DES PIÈCES (générique)
    // ════════════════════════════════════════════════════════

    /** Colonnes réelles de la table des pièces (lues une fois par requête). */
    protected static function colonnesPieces(): array
    {
        static $colonnes = null;

        return $colonnes ??= Schema::getColumnListing((new PieceDossier)->getTable());
    }

    /**
     * ✅ AJOUT — Colonne qui rattache une pièce à son dossier.
     * Selon les bases : 'dossier_id' (celle du modèle DossierFournisseur::pieces())
     * ou 'dossier_fournisseur_id'. Le service détecte la colonne réelle.
     */
    public static function colonneDossier(): string
    {
        return in_array('dossier_id', static::colonnesPieces(), true)
            ? 'dossier_id'
            : 'dossier_fournisseur_id';
    }

    /**
     * ✅ AJOUT — Crée une pièce dans un dossier en n'écrivant que les colonnes existantes.
     * Utilisé pour les pièces automatiques et les pièces ajoutées à la main.
     */
    public static function creerPiece(DossierFournisseur $dossier, array $donnees): PieceDossier
    {
        $donnees = [static::colonneDossier() => $dossier->id] + $donnees;
        $donnees = array_intersect_key($donnees, array_flip(static::colonnesPieces()));

        $piece = new PieceDossier();
        $piece->forceFill($donnees)->save();

        return $piece;
    }

    // ════════════════════════════════════════════════════════
    // AJOUTER UNE PIÈCE AUTOMATIQUEMENT
    // ════════════════════════════════════════════════════════

    public static function ajouterPieceAutomatique(
        DossierFournisseur $dossier,
        string $typePiece,
        $document,
        string $nomFichier = ''
    ): ?PieceDossier {

        $documentType = get_class($document);
        $documentId   = $document->id;

        // ✅ Ne pas dupliquer — vérifier si la pièce existe déjà
        // ✅ CORRIGÉ — colonne de rattachement réelle de la base
        $existe = PieceDossier::where(static::colonneDossier(), $dossier->id)
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->exists();

        if ($existe) return null;

        $donnees = [
            static::colonneDossier() => $dossier->id,
            'type_piece'             => $typePiece,
            'document_type'          => $documentType,
            'document_id'            => $documentId,
            'nom_fichier'            => $nomFichier ?: (static::TYPES[$typePiece] ?? $typePiece) . ' — ' . ($document->numero ?? $document->id),
            'chemin_fichier'         => '',
            'valide'                 => true,
            'valide_par'             => auth()->id() ?? $dossier->responsable_id,
            'date_validation'        => now(),
            'libelle'                => (static::TYPES[$typePiece] ?? $typePiece) . ' N° ' . ($document->numero ?? ''),
            'ajoute_par'             => auth()->id() ?? $dossier->responsable_id,
            'date_ajout'             => now(),
        ];

        // ✅ CORRIGÉ — uniquement les colonnes présentes dans la table (structure variable selon
        //    les bases : 'libelle', 'ajoute_par', 'date_ajout'... peuvent ne pas exister)
        $donnees = array_intersect_key($donnees, array_flip(static::colonnesPieces()));

        $piece = new PieceDossier();
        $piece->forceFill($donnees)->save();

        Log::info("Pièce [{$typePiece}] ajoutée au dossier {$dossier->numero_dossier}", [
            'document' => $document->numero ?? $documentId,
        ]);

        return $piece;
    }

    // ════════════════════════════════════════════════════════
    // SUPPRIMER UNE PIÈCE QUAND LE DOCUMENT SOURCE EST SUPPRIMÉ
    // ════════════════════════════════════════════════════════

    public static function supprimerPieceParSource(string $documentType, int $documentId): void
    {
        $pieces = PieceDossier::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->get();

        foreach ($pieces as $piece) {
            // ✅ CORRIGÉ — dossier retrouvé par la colonne réelle de la base
            $dossier = DossierFournisseur::find($piece->{static::colonneDossier()});
            $piece->delete();

            // ✅ Si le dossier n'a plus aucune pièce automatique,
            //    le remettre en brouillon plutôt que de le supprimer
            if ($dossier && $dossier->pieces()->count() === 0) {
                $dossier->update(['statut' => 'annule']);
                Log::info("Dossier {$dossier->numero_dossier} annulé — plus aucune pièce");
            }
        }

        if ($pieces->isNotEmpty()) {
            Log::info("Pièce(s) supprimée(s) du dossier fournisseur", [
                'document_type' => $documentType,
                'document_id'   => $documentId,
                'nb_pieces'     => $pieces->count(),
            ]);
        }
    }

    // ════════════════════════════════════════════════════════
    // MÉTHODE PRINCIPALE — appelée depuis les observers
    // ════════════════════════════════════════════════════════

    /**
     * Traitement complet quand un BC est engagé :
     * Crée le dossier + ajoute le BC comme pièce
     */
    public static function traiterEngagementBC(\App\Models\BonCommande $bc): void
    {
        if (!$bc->fournisseur_id) return;

        try {
            $dossier = static::obtenirOuCreer($bc->fournisseur_id, $bc->exercice_id, $bc);
            static::ajouterPieceAutomatique($dossier, 'bon_commande', $bc, "BC-{$bc->numero}.pdf");
        } catch (\Exception $e) {
            Log::error("Erreur création dossier pour BC {$bc->numero} : " . $e->getMessage());
        }
    }

    /**
     * Traitement complet quand une DA est engagée :
     * Crée le dossier + ajoute la DA + le CE comme pièces
     */
    public static function traiterEngagementDA(\App\Models\DecisionAdministrative $da, \App\Models\Engagement $engagement): void
    {
        // ✅ Déterminer le fournisseur depuis la DA (peut être un personnel)
        $fournisseurId = $da->fournisseur_id ?? null;
        if (!$fournisseurId) return; // DA de personnel : pas de dossier fournisseur

        try {
            $dossier = static::obtenirOuCreer($fournisseurId, $da->exercice_id, $da);

            // Ajouter la DA
            static::ajouterPieceAutomatique($dossier, 'decision_administrative', $da, "DA-{$da->numero}.pdf");

            // Ajouter le Certificat d'Engagement
            static::ajouterPieceAutomatique($dossier, 'certificat_engagement', $engagement, "CE-{$engagement->numero}.pdf");
        } catch (\Exception $e) {
            Log::error("Erreur création dossier pour DA {$da->numero} : " . $e->getMessage());
        }
    }

    /**
     * Ajouter le CE au dossier quand un BC est engagé
     */
    public static function ajouterCEAuDossierBC(\App\Models\BonCommande $bc, \App\Models\Engagement $engagement): void
    {
        if (!$bc->fournisseur_id) return;

        try {
            // ✅ MODIFIÉ — dossier du fournisseur actuel du BC
            $dossier = static::dossierDuDocument($bc);

            if ($dossier) {
                static::ajouterPieceAutomatique($dossier, 'certificat_engagement', $engagement, "CE-{$engagement->numero}.pdf");
            }
        } catch (\Exception $e) {
            Log::error("Erreur ajout CE au dossier BC {$bc->numero} : " . $e->getMessage());
        }
    }

    /**
     * Ajouter une OP/OPT au dossier fournisseur correspondant
     */
    public static function ajouterOPAuDossier(\App\Models\OrdonnancePaiement $op): void
    {
        try {
            $engagement = $op->engagement;
            if (!$engagement) return;

            $documentSource = $engagement->engageable;
            if (!$documentSource) return;

            // ✅ MODIFIÉ — dossier du fournisseur actuel du document source
            $dossier = static::dossierDuDocument($documentSource);

            if (!$dossier) return;

            $typePiece = $op->type_ordonnance === 'impot'
                ? 'ordonnance_impot'
                : 'ordonnance_paiement';

            $prefix = $op->type_ordonnance === 'impot' ? 'OPT' : 'OP';

            static::ajouterPieceAutomatique(
                $dossier,
                $typePiece,
                $op,
                "{$prefix}-{$op->numero}.pdf"
            );

            // ✅ Mettre à jour montant payé si OP payée
            if ($op->statut === 'payee' && $op->type_ordonnance === 'standard') {
                $dossier->increment('montant_paye', $op->montant_net);
            }
        } catch (\Exception $e) {
            Log::error("Erreur ajout OP {$op->numero} au dossier : " . $e->getMessage());
        }
    }
}
