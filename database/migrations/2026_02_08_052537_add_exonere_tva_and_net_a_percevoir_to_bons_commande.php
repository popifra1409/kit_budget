<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            // Exonération TVA
            if (!Schema::hasColumn('bons_commande', 'exonere_tva')) {
                $table->boolean('exonere_tva')
                    ->default(false)
                    ->after('produit_importe')
                    ->comment('Indique si le BC est exonéré de TVA');
            }

            // Net à percevoir (pour les ordonnances)
            if (!Schema::hasColumn('bons_commande', 'net_a_percevoir')) {
                $table->decimal('net_a_percevoir', 15, 2)
                    ->default(0)
                    ->after('net_a_payer')
                    ->comment('Montant net à percevoir par le fournisseur (HT - IR)');
            }

            // Montant TSR (Taxe Statistique Régionale)
            if (!Schema::hasColumn('bons_commande', 'montant_tsr')) {
                $table->decimal('montant_tsr', 15, 2)
                    ->default(0)
                    ->after('montant_ir')
                    ->comment('Montant de la Taxe Statistique Régionale');
            }

            // Montant CNPS
            if (!Schema::hasColumn('bons_commande', 'montant_cnps')) {
                $table->decimal('montant_cnps', 15, 2)
                    ->default(0)
                    ->after('montant_tsr')
                    ->comment('Montant des cotisations CNPS');
            }

            // Montant IRNC
            if (!Schema::hasColumn('bons_commande', 'montant_irnc')) {
                $table->decimal('montant_irnc', 15, 2)
                    ->default(0)
                    ->after('montant_cnps')
                    ->comment('Montant de l\'Impôt sur le Revenu Non Commercial');
            }

            // Montant autres taxes
            if (!Schema::hasColumn('bons_commande', 'montant_autres_taxes')) {
                $table->decimal('montant_autres_taxes', 15, 2)
                    ->default(0)
                    ->after('montant_irnc')
                    ->comment('Montant des autres taxes et prélèvements');
            }

            // Produit importé
            if (!Schema::hasColumn('bons_commande', 'produit_importe')) {
                $table->boolean('produit_importe')
                    ->default(false)
                    ->after('montant_autres_taxes')
                    ->comment('Indique si les produits sont importés (soumis à TSR)');
            }

            // Net à payer
            if (!Schema::hasColumn('bons_commande', 'net_a_payer')) {
                $table->decimal('net_a_payer', 15, 2)
                    ->default(0)
                    ->after('montant_ttc')
                    ->comment('Montant net à payer (après déductions)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $columns = [
                'exonere_tva',
                'net_a_percevoir',
                'montant_tsr',
                'montant_cnps',
                'montant_irnc',
                'montant_autres_taxes',
                'produit_importe',
                'net_a_payer',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('bons_commande', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
