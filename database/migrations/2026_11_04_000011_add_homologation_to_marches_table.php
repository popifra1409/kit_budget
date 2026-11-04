<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape E 2/3 : l'homologation, posée sur le marché.
 *
 * L'homologation est la décision qui transforme l'attribution provisoire en marché
 * homologué, puis en marché en cours d'exécution. Comme celle de l'étape D, elle
 * n'a pas de table : c'est un état du marché, et ce qu'elle décide tient en deux
 * colonnes.
 *
 * Pourquoi seulement deux, alors que la migration 000009 en posait six :
 *
 * 1. L'attribution choisissait — il lui fallait l'offre retenue, le montant figé, et
 *    la trace du choix contre le classement. L'homologation ne choisit personne :
 *    elle approuve ce qui est déjà écrit. L'attributaire, le montant et le justificatif
 *    restent ceux de l'attribution, recopiés dans une colonne d'homologation ils
 *    deviendraient la seconde main sur un même fait.
 *
 * 2. Pas de « numéro de la décision d'homologation » saisi à la main. Une référence
 *    tapée au clavier ne prouve rien ; ce qui se lit dans un recours, c'est qui a
 *    homologué et à quelle heure — les deux colonnes posées ici. La référence de
 *    l'arrêté viendra avec l'écran qui la reçoit, pas avant.
 *
 * 3. La machine à états avait déjà `homologue` et `en_execution` dans le vocabulaire
 *    de la migration 000006, et `attribue → homologue` dans les transitions :
 *    aucune contrainte CHECK de statut à élargir, donc aucune migration de
 *    `marches.statut`. Le retour `homologue → attribue` est ajouté dans
 *    config/marches.php, où vivent les transitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marches', function (Blueprint $table) {
            $table->foreignId('homologue_par_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Qui a homologué l\'attribution — la main que relit un recours');
            $table->timestamp('date_homologation')->nullable();

            $table->index('homologue_par_id');
        });

        // Un « homologué » sans auteur ni date n'existe pas : la colonne est lue
        // sous ce mot par l'écran, la fiche de suivi et les ordres de service.
        DB::statement("
            ALTER TABLE marches
                ADD CONSTRAINT marches_homologue_trace_check
                CHECK (statut <> 'homologue' OR (homologue_par_id IS NOT NULL AND date_homologation IS NOT NULL))
        ");

        // Les deux colonnes vont ensemble dans les deux sens : une date sans main,
        // ou une main sans date, ne racontent rien — et se laisseraient corriger
        // l'une sans l'autre.
        DB::statement("
            ALTER TABLE marches
                ADD CONSTRAINT marches_homologation_paire_check
                CHECK (
                    (homologue_par_id IS NULL AND date_homologation IS NULL)
                    OR (homologue_par_id IS NOT NULL AND date_homologation IS NOT NULL)
                )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE marches DROP CONSTRAINT IF EXISTS marches_homologue_trace_check');
        DB::statement('ALTER TABLE marches DROP CONSTRAINT IF EXISTS marches_homologation_paire_check');

        Schema::table('marches', function (Blueprint $table) {
            $table->dropColumn(['homologue_par_id', 'date_homologation']);
        });
    }
};
