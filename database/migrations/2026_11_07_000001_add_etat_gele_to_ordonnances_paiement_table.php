<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Volet 2 de l'immuabilité des états imprimés — le gel à l'émission, côté ordonnances.
 *
 * Le certificat d'engagement est gelé depuis la migration précédente. L'ordonnance qui
 * le suit, elle, ne se gèle pas : elle relit à chaque impression le bon de commande ou la
 * décision administrative vivant. Or l'ordonnance est le papier que l'agence comptable
 * paie. Un avenant au BC, une DA corrigée, un bénéficiaire dont l'adresse change, et la
 * ré-impression d'une ordonnance déjà payée ne dit plus la même chose que celle qui a
 * circulé.
 *
 * Ce que l'OP imprime et qui peut bouger après coup :
 *  - le brut, le net et le total des retenues. Les colonnes `montant_brut`/`montant_net`
 *    de l'OP ne sont pas une mémoire : 951 ordonnances sur 1046 portent un brut à zéro,
 *    dont 535 OP standard. Ces colonnes ne sont pas ce que le papier imprimait ;
 *  - le détail des retenues et les taux affichés dans les libellés (IR, TVA, TSR, CNPS,
 *    IRNC, FEICOM, redevance, autres), recalculés depuis le barème ou le document source ;
 *  - l'imputation : codes et libellés de la hiérarchie programme → action → activité →
 *    tâche, plus chapitre / paragraphe / article. Une nomenclature se re-libelle ;
 *  - l'identité telle que tapée sur le papier : bénéficiaire (nom, adresse, ville) et,
 *    pour l'OP d'impôt, le reverseur ;
 *  - banque et billetage, lus sur la DA, et le montant de l'engagement imprimé en bloc
 *    budgétaire.
 *
 * Ce que ce gel ne reprend PAS, délibérément : la dotation, les collectifs, l'engagé
 * avant et les disponibles. Le gabarit d'ordonnance lit déjà ces chiffres par
 * `HistoriqueLigneBudgetaireService::etatPourEngagement()`, c'est-à-dire le gel du
 * certificat. Leur refaire une copie ici, ce serait deux vérités sur deux papiers du
 * même circuit — et la question « l'OP et le CE disent-ils la même dotation ? »
 * deviendrait une question de synchronisation au lieu d'être une évidence.
 *
 * Huit colonnes, pas une de moins : six qui se lisent ensemble (le total des retenues
 * sans le détail ne se vérifie pas, un brut sans le net ne se justifie pas) et
 * `snapshot_fige_le`, qui distingue l'émission d'une OP d'une reconstruction pour les
 * ordonnances nées avant ce gel.
 *
 * La précision des montants reprend celle de `ordonnances_paiement.montant_brut`
 * (numeric(15,2)) : un gel plus large que la valeur qu'il fige serait une seconde vérité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->decimal('snapshot_montant_brut', 15, 2)->nullable()
                ->comment('Brut tel que le papier l\'imprime, non la colonne montant_brut de l\'OP');
            $table->decimal('snapshot_montant_net', 15, 2)->nullable()
                ->comment('Net à payer signé par l\'ordonnateur');
            $table->decimal('snapshot_montant_retenu', 15, 2)->nullable()
                ->comment('Somme des retenues imprimées sur le document');
            $table->json('snapshot_montants')->nullable()
                ->comment('Imputation, montant de l\'engagement, banque et billetage imprimés');
            $table->json('snapshot_retenues')->nullable()
                ->comment('Détail IR / TVA / TSR / CNPS / IRNC / FEICOM et taux affichés');
            $table->json('snapshot_imputation')->nullable()
                ->comment('Codes et libellés de la hiérarchie budgétaire, tels que tapés');
            $table->json('snapshot_identites')->nullable()
                ->comment('Bénéficiaire et reverseur tels que le papier les nomme');
            $table->timestamp('snapshot_fige_le')->nullable()
                ->comment('Émission pour les ordonnances nées après le gel, reconstruction pour les autres');
        });

        // Un gel à moitié rempli n'existe pas : huit colonnes qui se tiennent ou rien.
        // Une retenue sans son total, ou une identité sans son adresse, voudrait dire un
        // papier dont une partie se recalcule encore — le trou que ce volet ferme.
        DB::statement("
            ALTER TABLE ordonnances_paiement
                ADD CONSTRAINT ordonnances_paiement_etat_gele_bloc_check
                CHECK (
                    (
                        snapshot_montant_brut IS NULL
                        AND snapshot_montant_net IS NULL
                        AND snapshot_montant_retenu IS NULL
                        AND snapshot_montants IS NULL
                        AND snapshot_retenues IS NULL
                        AND snapshot_imputation IS NULL
                        AND snapshot_identites IS NULL
                        AND snapshot_fige_le IS NULL
                    )
                    OR (
                        snapshot_montant_brut IS NOT NULL
                        AND snapshot_montant_net IS NOT NULL
                        AND snapshot_montant_retenu IS NOT NULL
                        AND snapshot_montants IS NOT NULL
                        AND snapshot_retenues IS NOT NULL
                        AND snapshot_imputation IS NOT NULL
                        AND snapshot_identites IS NOT NULL
                        AND snapshot_fige_le IS NOT NULL
                    )
                )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ordonnances_paiement DROP CONSTRAINT IF EXISTS ordonnances_paiement_etat_gele_bloc_check');

        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->dropColumn([
                'snapshot_montant_brut',
                'snapshot_montant_net',
                'snapshot_montant_retenu',
                'snapshot_montants',
                'snapshot_retenues',
                'snapshot_imputation',
                'snapshot_identites',
                'snapshot_fige_le',
            ]);
        });
    }
};
