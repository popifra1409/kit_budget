<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligne la contrainte des types de pièces (pieces_dossier.type_piece) sur les types
 * utilisés par DossierFournisseurService (DA, certificat d'engagement, OP, OP impôt...).
 *
 * GÉNÉRIQUE : la contrainte existante est LUE, puis ÉLARGIE — aucune valeur déjà autorisée
 * n'est retirée. Sans contrainte (ou hors PostgreSQL), la migration ne fait rien.
 */
return new class extends Migration
{
    protected const TABLE = 'pieces_dossier';

    /** Types utilisés par l'application (service, ajout manuel, création de dossier BC). */
    protected const TYPES_REQUIS = [
        'bon_commande',
        'decision_administrative',
        'certificat_engagement',
        'engagement',
        'ordonnance_paiement',
        'ordonnance_impot',
        'facture_proforma',
        'facture_definitive',
        'bon_livraison',
        'pv_reception',
        'certificat_service_fait',
        'justificatif_paiement',
        'contrat',
        'autre',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $contrainte = DB::selectOne(
            "SELECT c.conname AS nom, pg_get_constraintdef(c.oid) AS definition
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
              WHERE t.relname = ? AND c.contype = 'c'
                AND pg_get_constraintdef(c.oid) LIKE '%type_piece%'",
            [self::TABLE]
        );

        if (!$contrainte) {
            return; // aucune contrainte sur type_piece : toutes les valeurs sont déjà acceptées
        }

        preg_match_all("/'([^']+)'::/", $contrainte->definition, $m);
        $valeurs = array_values(array_unique(array_merge($m[1] ?? [], self::TYPES_REQUIS)));

        $liste = implode(', ', array_map(fn($v) => DB::getPdo()->quote($v), $valeurs));

        DB::transaction(function () use ($contrainte, $liste) {
            DB::statement('ALTER TABLE ' . self::TABLE . ' DROP CONSTRAINT "' . $contrainte->nom . '"');
            DB::statement(
                'ALTER TABLE ' . self::TABLE . ' ADD CONSTRAINT "' . $contrainte->nom . '" '
                    . "CHECK ((type_piece)::text = ANY (ARRAY[{$liste}]::text[]))"
            );
        });
    }

    public function down(): void
    {
        // Volontairement sans effet : restreindre la liste échouerait si des pièces
        // utilisent déjà les nouveaux types.
    }
};
