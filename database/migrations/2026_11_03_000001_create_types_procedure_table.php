<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — paramétrage 1/5.
 *
 * « Caractéristique du marché : type de procédure » (scans du 27/10). La procédure
 * est saisie comme feuille (Appel d'offres ouvert, Appel d'offres restreint,
 * Demande de cotation, Gré à gré) et non comme un arbre : la colonne « famille »
 * porte le regroupement, ce qui garde le référentiel à plat et le Select du
 * marché mono-niveau. C'est la feuille qui détermine le contrat attendu
 * (une cotation donne une lettre de commande), donc la valeur est contrainte
 * en base par le vocabulaire de config('marches.familles_procedure').
 *
 * Semis : TypesProcedureSeeder (additif, modifiable à l'écran ensuite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_procedure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->enum('famille', array_keys(config('marches.familles_procedure')))
                ->comment('Regroupement affiché dans le paramétrage : appel_offre | cotation | gre_a_gre');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index('famille');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_procedure');
    }
};
