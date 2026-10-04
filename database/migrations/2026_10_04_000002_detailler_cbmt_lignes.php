<?php
// database/migrations/2026_10_04_000002_detailler_cbmt_lignes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CBMT détaillé PAR LIGNE de nomenclature (et non plus un total par titre) :
 * les titres regroupent les lignes budgétaires et de recettes réelles.
 *
 * Compatible avec l'existant : montant_n (prévision N actualisée) et montant_n_plus_1..3
 * (projections) gardent leur rôle ; les totaux par titre restent des sommes de lignes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbmt_lignes', function (Blueprint $table) {
            $table->foreignId('nomenclature_id')->nullable()->after('nature')
                ->constrained('nomenclature_budgetaire')->nullOnDelete();
            $table->string('code', 20)->nullable()->after('nomenclature_id');
            $table->string('libelle')->nullable()->after('code');
            $table->string('type_ligne', 2)->default('LR')->after('libelle');      // LR / MN

            $table->decimal('prevision_n_initiale', 18, 2)->default(0)->after('montant_n_moins_1');
            $table->decimal('realisation_n', 18, 2)->default(0)->after('montant_n');            // recouvré / engagé
            $table->decimal('realisation_n_ordonnance', 18, 2)->default(0)->after('realisation_n'); // dépenses
            $table->unsignedInteger('ordre')->default(0);

            $table->unique(['cbmt_exercice_id', 'nature', 'nomenclature_id'], 'cbmt_lignes_unique_compte');
        });

        // Le titre d'une ligne est déduit de sa nomenclature : il peut être vide (ligne non classée)
        Schema::table('cbmt_lignes', function (Blueprint $table) {
            $table->unsignedTinyInteger('titre')->nullable()->change();
            $table->string('libelle_titre')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cbmt_lignes', function (Blueprint $table) {
            $table->dropUnique('cbmt_lignes_unique_compte');
            $table->dropConstrainedForeignId('nomenclature_id');
            $table->dropColumn(['code', 'libelle', 'type_ligne', 'prevision_n_initiale', 'realisation_n', 'realisation_n_ordonnance', 'ordre']);
        });
    }
};
