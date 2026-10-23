<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'état du compte d'emploi d'une régie doit être précédé de la liste des pièces
 * qui le fondent (résolution de renouvellement, décisions d'ouverture et de
 * désignation, mandat de décaissement, quittance de reversement). Ces références
 * vivaient jusqu'ici dans un textarea recopié à la main à l'impression : elles
 * deviennent des données de la fiche régie et de la tranche, et l'état les
 * compose lui-même.
 *
 * Tout est nullable : une régie créée sans résolution n'imprime pas la ligne
 * correspondante plutôt que d'afficher « Résolution n°  du  ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regies_avances', function (Blueprint $table) {
            $table->string('resolution_numero', 120)->nullable();
            $table->date('resolution_date')->nullable();

            $table->string('decision_ouverture_numero', 120)->nullable();
            $table->date('decision_ouverture_date')->nullable();

            $table->string('decision_designation_numero', 120)->nullable();
            $table->date('decision_designation_date')->nullable();
        });

        Schema::table('decaissements_regies', function (Blueprint $table) {
            // certificat_numero existait déjà mais n'est plus saisi depuis
            // l'action « Accorder & Verser » : la date qui va avec manquait.
            $table->date('certificat_date')->nullable();

            $table->string('mandat_numero', 120)->nullable();
            $table->date('mandat_date')->nullable();

            $table->string('quittance_numero', 120)->nullable();
            $table->date('quittance_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('regies_avances', function (Blueprint $table) {
            $table->dropColumn([
                'resolution_numero', 'resolution_date',
                'decision_ouverture_numero', 'decision_ouverture_date',
                'decision_designation_numero', 'decision_designation_date',
            ]);
        });

        Schema::table('decaissements_regies', function (Blueprint $table) {
            $table->dropColumn([
                'certificat_date',
                'mandat_numero', 'mandat_date',
                'quittance_numero', 'quittance_date',
            ]);
        });
    }
};
