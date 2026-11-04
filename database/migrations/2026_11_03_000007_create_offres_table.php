<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape C : la réception des offres (scan du 27/10 17.29).
 *
 * Le scan dicte la saisie, ligne par ligne : « renseigner les informations du
 * soumissionnaire · choisir le marché correspondant · date de réception · upload
 * de la quittance de paiement de marché ou les informations sur la quittance ·
 * validation ». Les colonnes sont donc celles-là, et rien d'autre.
 *
 * Quatre partis pris :
 *
 * 1. Le soumissionnaire est une référence au référentiel `fournisseurs`, pas une
 *    recopie. « Renseigner les informations » veut dire choisir la fiche, dont
 *    l'écran affiche alors le RCCM, le NIF et la radiation éventuelle : une offre
 *    saisie avec un nom tapé à la main ne se rapproche jamais d'une blacklist.
 *
 * 2. La quittance tient dans quatre colonnes de cette table (numéro, date,
 *    montant, fichier) et non dans une table de documents : `pieces_dossier` est
 *    clef sur `dossier_id → dossiers_fournisseurs` (une pièce de l'offre n'est pas
 *    une pièce du dossier fournisseur), et une table générique polymorphe n'aurait
 *    encore qu'un seul type d'usage. Elle viendra avec l'étape qui aura deux
 *    sortes de pièces à stocker — pas avant.
 *
 * 3. Une offre ne se décide pas deux fois : `recue → validee | rejetee`, et
 *    `validee | rejetee → recue` seulement. La colonne `motif_rejet` est tenue
 *    par la base, comme le justificatif de classement de l'étape B : le motif
 *    est ce que lit le soumissionnaire qui conteste.
 *
 * 4. Un même soumissionnaire ne dépose qu'une offre par marché, contrainte
 *    d'unicité partielle : `WHERE deleted_at IS NULL`, pour qu'une offre retirée
 *    du dossier ne bloque pas le redépôt qui suivrait sa correction.
 *
 * Les étapes D à F branchent ici sans modifier cette table : évaluations (D) sur
 * offre_id, attribution (D) sur le marché, ordres de service (E) et engagement
 * budgétaire (F) sur marches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offres', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 30)->unique()
                ->comment('Référence SIGB, générée par Offre::genererNumero() — OFF-2026-0001');

            // — Choisir le marché correspondant · le soumissionnaire
            $table->foreignId('marche_id')->constrained('marches')->restrictOnDelete()
                ->comment('Le marché dont l\'avis de lancement a fait courir le dépôt');
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->restrictOnDelete()
                ->comment('Le soumissionnaire, référencé : ni recopie ni doublon anonyme');
            $table->date('date_depot')
                ->comment('Date de réception au service des marchés (scan : « date de réception »)');

            // — Le contenu de l'offre
            $table->decimal('montant_offert', 18, 2)->default(0)
                ->comment('0 = pas encore saisi ; la validation l\'exige');
            $table->integer('delai_propose')->nullable()
                ->comment('Délai d\'exécution proposé, en mois');

            // — La quittance de paiement (upload ou saisie de ses informations)
            $table->string('numero_quittance', 50)->nullable();
            $table->date('date_quittance')->nullable();
            $table->decimal('montant_quittance', 18, 2)->nullable();
            $table->string('fichier_quittance')->nullable()
                ->comment('Chemin sur le disque public, dossier marches/offres — pas une URL');

            // — La décision
            $table->enum('statut', array_keys(config('marches.statuts_offre')))
                ->default('recue')
                ->comment('recue | validee | rejetee — machine à états dans Offre');
            $table->foreignId('valide_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_validation')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->foreignId('rejet_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_rejet')->nullable();

            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('marche_id');
            $table->index('fournisseur_id');
            $table->index('statut');
            $table->index('date_depot');
            $table->index(['marche_id', 'statut']);
        });

        // Une offre par soumissionnaire et par marché, tant qu'elle est au dossier.
        DB::statement('
            CREATE UNIQUE INDEX offres_marche_fournisseur_unique
                ON offres (marche_id, fournisseur_id)
                WHERE deleted_at IS NULL
        ');

        // Une offre recevable a payé sa participation : la quittance est là.
        // IS NOT NULL d'abord, sinon le btrim(NULL) <> '' de la colonne vide évalue
        // NULL et la contrainte laisse passer ce qu'elle existe à empêcher.
        DB::statement("
            ALTER TABLE offres
                ADD CONSTRAINT offres_validee_quittance_check
                CHECK (
                    statut <> 'validee'
                    OR (numero_quittance IS NOT NULL AND btrim(numero_quittance) <> ''
                        AND date_quittance IS NOT NULL)
                )
        ");

        // Un rejet se motive.
        DB::statement("
            ALTER TABLE offres
                ADD CONSTRAINT offres_rejet_motif_check
                CHECK (
                    statut <> 'rejetee'
                    OR (motif_rejet IS NOT NULL AND btrim(motif_rejet) <> '')
                )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('offres');
    }
};
