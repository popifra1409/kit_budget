<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La régie se consomme à L'ENGAGEMENT du bon de commande (statut « engagé ») et à
 * la validation de l'achat direct — pas à la livraison ni au paiement.
 *
 * Jusqu'ici seul le « dépensé » (livré/payé) était sommé : un BCR engagé mais pas
 * encore livré laissait donc le Disponible de la régie intact, et l'écran
 * prétendait que l'argent était encore employable alors que l'encaisse était déjà
 * réservé. La colonne « engagé » porte la consommation réelle, celle que les
 * blocages de plafond utilisent déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE decaissements_regies
            ADD COLUMN IF NOT EXISTS montant_engage NUMERIC(18,2) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE regies_avances
            ADD COLUMN IF NOT EXISTS montant_engage NUMERIC(18,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE decaissements_regies DROP COLUMN IF EXISTS montant_engage');
        DB::statement('ALTER TABLE regies_avances DROP COLUMN IF EXISTS montant_engage');
    }
};
