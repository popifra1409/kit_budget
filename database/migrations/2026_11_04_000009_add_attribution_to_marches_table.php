<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape D 2/2 : l'attribution, posée sur le marché.
 *
 * L'attribution n'a pas de table : elle est un état du marché, atteint par le
 * cycle (en_evaluation → attribué), et tout ce qu'elle décide tient en cinq
 * colonnes. Une table `attributions` aurait un seul usage, comme la table de
 * pièces de l'étape C — et elle autoriserait deux attributions simultanées sur un
 * même DAO, ce que la procédure interdit.
 *
 * Trois partis pris :
 *
 * 1. `offre_attribuee_id` seulement : l'attributaire est le soumissionnaire de
 *    cette offre, il ne se recopie pas. Une colonne « attributaire » serait une
 *    seconde main sur le même fait, capable de le contredire quand l'offre est
 *    corrigée.
 *
 * 2. `montant_attribue` est une photographie du montant offert, pas une vue sur
 *    l'offre : une correction de saisie postérieure ne doit pas déplacer la
 *    décision prise ce jour-là, et le rapport d'attribution se relit avec ce
 *    montant-là.
 *
 * 3. `attribution_manuelle` + `attribution_justificatif` reprennent à l'identique
 *    le mécanisme du classement contre le seuil (étape B) : quand le choix contredit
 *    le classement sorti des notes, la main qui choisit le justifie, ou le choix
 *    n'est pas enregistré. La contrainte CHECK est la même, et pour la même raison.
 *
 * Le statut `attribue` était déjà dans le vocabulaire de la migration 000006 : la
 * machine à états est inchangée, seule une action l'atteint maintenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marches', function (Blueprint $table) {
            $table->foreignId('offre_attribuee_id')->nullable()
                ->constrained('offres')->restrictOnDelete()
                ->comment('L\'offre retenue — l\'attributaire s\'en déduit, il ne se saisit jamais');
            $table->decimal('montant_attribue', 18, 2)->nullable()
                ->comment('Photographie du montant offert au jour de l\'attribution');
            $table->foreignId('attribue_par_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('date_attribution')->nullable();

            $table->boolean('attribution_manuelle')->default(false)
                ->comment('Vrai quand l\'offre retenue n\'est pas la mieux notée');
            $table->text('attribution_justificatif')->nullable();

            $table->index('offre_attribuee_id');
        });

        // Un « attribué » sans attributaire n'existe pas : la colonne est lue par
        // l'écran, le PV et l'engagement budgétaire (étape F) sous ce mot.
        DB::statement("
            ALTER TABLE marches
                ADD CONSTRAINT marches_attribue_offre_check
                CHECK (statut <> 'attribue' OR offre_attribuee_id IS NOT NULL)
        ");

        // Une attribution non tracée n'existe pas davantage : pas d'auteur ni de
        // date sans offre retenue, et inversement.
        DB::statement("
            ALTER TABLE marches
                ADD CONSTRAINT marches_attribution_tracee_check
                CHECK (
                    offre_attribuee_id IS NULL
                    OR (attribue_par_id IS NOT NULL AND date_attribution IS NOT NULL)
                )
        ");

        // Choisir contre le classement se justifie, à la seconde près du classement
        // manuel de l'étape B — mêmes colonnes, même motif, même contrainte.
        DB::statement("
            ALTER TABLE marches
                ADD CONSTRAINT marches_attribution_justifiee_check
                CHECK (
                    attribution_manuelle = false
                    OR (attribution_justificatif IS NOT NULL
                        AND btrim(attribution_justificatif) <> '')
                )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE marches DROP CONSTRAINT IF EXISTS marches_attribue_offre_check');
        DB::statement('ALTER TABLE marches DROP CONSTRAINT IF EXISTS marches_attribution_tracee_check');
        DB::statement('ALTER TABLE marches DROP CONSTRAINT IF EXISTS marches_attribution_justifiee_check');

        Schema::table('marches', function (Blueprint $table) {
            $table->dropColumn([
                'offre_attribuee_id', 'montant_attribue', 'attribue_par_id',
                'date_attribution', 'attribution_manuelle', 'attribution_justificatif',
            ]);
        });
    }
};
