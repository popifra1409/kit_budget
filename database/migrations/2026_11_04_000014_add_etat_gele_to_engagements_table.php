<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Volet 2 de l'immuabilité des états imprimés — le gel à l'émission.
 *
 * Le volet 1 (coupure chronologique) recompose l'état de la ligne au jour du papier.
 * Il ne peut que raisonner à partir de ce qui est encore en base. Or :
 *  - Engagement::annuler() est un forceDelete() : la ligne a disparu, l'« engagé avant »
 *    du papier est irrécupérable ;
 *  - un virement peut être corrigé, re-daté, annulé — quatre mois après le visa ;
 *  - une ré-impression de tribunal ne peut pas dépendre de l'état du fichier à l'instant t.
 *
 * Ces colonnes ne sont pas un cache de performance : elles sont la copie de ce que le
 * papier dit. Elles se posent au moment où le document naît (EngagementObserver) et ne
 * se défont que si le document lui-même change de consistance — date, montant, budget ou
 * ligne. Un virement postérieur, une annulation, une correction d'écriture : le gel reste.
 *
 * Sept colonnes, pas une de moins :
 *  - la dotation (initiale, puis après modifications) et la liste des modifications
 *    imprimées. Sans la liste, un collectif annulé depuis effacerait sa propre ligne du
 *    papier, alors que le montant, lui, resterait — le document ne se relirait plus ;
 *  - l'engagé avant et les deux disponibles, qui sont ce que l'ordonnateur signe ;
 *  - snapshot_fige_le : ce que le document doit à la reconstruction (backfill) et ce
 *    qu'il doit à son émission se lit là, pas dans une supputation.
 *
 * Les montants reprennent la précision de `engagements.montant_engage` (numeric(15,2)) :
 * un gel plus large que la valeur qu'il fige serait une seconde vérité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->decimal('snapshot_budget_initial', 15, 2)->nullable()
                ->comment('Dotation initiale de la ligne telle que le papier l\'imprime');
            $table->decimal('snapshot_budget_rectifie', 15, 2)->nullable()
                ->comment('Dotation après les modifications antérieures au jour du papier');
            $table->json('snapshot_modifications')->nullable()
                ->comment('Lignes de collectif et de virement imprimées avec le document');
            $table->decimal('snapshot_total_engage_avant', 15, 2)->nullable()
                ->comment('Somme engagée sur la ligne avant ce document, ce jour-là');
            $table->decimal('snapshot_disponible_avant', 15, 2)->nullable()
                ->comment('Le montant disponible que l\'ordonnateur a visé');
            $table->decimal('snapshot_disponible_apres', 15, 2)->nullable()
                ->comment('Le nouveau montant disponible que le papier annonce');
            $table->timestamp('snapshot_fige_le')->nullable()
                ->comment('Émission pour les documents nés après le gel, reconstruction pour les autres');
        });

        // Un gel à moitié rempli n'existe pas : six colonnes qui se tiennent ou rien.
        // Une écriture partielle voudrait dire un papier dont le disponible ne découle
        // plus de la dotation imprimée — précisément la dérive que cette table ferme.
        DB::statement("
            ALTER TABLE engagements
                ADD CONSTRAINT engagements_etat_gele_bloc_check
                CHECK (
                    (
                        snapshot_budget_initial IS NULL
                        AND snapshot_budget_rectifie IS NULL
                        AND snapshot_modifications IS NULL
                        AND snapshot_total_engage_avant IS NULL
                        AND snapshot_disponible_avant IS NULL
                        AND snapshot_disponible_apres IS NULL
                        AND snapshot_fige_le IS NULL
                    )
                    OR (
                        snapshot_budget_initial IS NOT NULL
                        AND snapshot_budget_rectifie IS NOT NULL
                        AND snapshot_modifications IS NOT NULL
                        AND snapshot_total_engage_avant IS NOT NULL
                        AND snapshot_disponible_avant IS NOT NULL
                        AND snapshot_disponible_apres IS NOT NULL
                        AND snapshot_fige_le IS NOT NULL
                    )
                )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE engagements DROP CONSTRAINT IF EXISTS engagements_etat_gele_bloc_check');

        Schema::table('engagements', function (Blueprint $table) {
            $table->dropColumn([
                'snapshot_budget_initial',
                'snapshot_budget_rectifie',
                'snapshot_modifications',
                'snapshot_total_engage_avant',
                'snapshot_disponible_avant',
                'snapshot_disponible_apres',
                'snapshot_fige_le',
            ]);
        });
    }
};
