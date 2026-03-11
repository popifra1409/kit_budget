<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════
            // 1. MODE DE SAISIE (standard ou alternatif)
            // ═══════════════════════════════════════════════════════════
            $table->string('mode_saisie_montants', 20)
                ->default('standard')
                ->after('montant_brut')
                ->comment('Mode : standard (Brut→HT) ou alternatif (HT décomposé)');

            // ═══════════════════════════════════════════════════════════
            // 2 & 3. MONTANTS HT DÉCOMPOSÉS (Mode Alternatif uniquement)
            // ═══════════════════════════════════════════════════════════
            $table->decimal('montant_ht_non_taxable', 15, 2)
                ->nullable()
                ->after('mode_saisie_montants')
                ->comment('Montant HT non soumis à la TVA (mode alternatif)');

            $table->decimal('montant_ht_taxable', 15, 2)
                ->nullable()
                ->after('montant_ht_non_taxable')
                ->comment('Montant HT soumis à la TVA (mode alternatif)');

            // ═══════════════════════════════════════════════════════════
            // 4. MONTANT IR EN FORFAIT (complète taux_irnc existant)
            // ═══════════════════════════════════════════════════════════
            $table->decimal('montant_ir_forfait', 15, 2)
                ->nullable()
                ->after('montant_irnc')
                ->comment('Montant IR en forfait (si mode forfait choisi)');
        });
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropColumn([
                'mode_saisie_montants',
                'montant_ht_non_taxable',
                'montant_ht_taxable',
                'montant_ir_forfait',
            ]);
        });
    }
};
