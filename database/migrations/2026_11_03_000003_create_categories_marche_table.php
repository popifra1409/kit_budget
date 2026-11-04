<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — paramétrage 3/5.
 *
 * Catégorie du marché demandée pour le paramétrage : pluriannuel, annuel, mensuel.
 *
 * « pluri_annuel » est séparé du code parce que c'est lui qui pilote un comportement
 * (engagement par tranches d'exercice — étape F), pas le libellé saisi à l'écran.
 * « duree_mois » n'est qu'une proposition de durée reprise à la création du marché :
 * le marché pourra la surcharger, un marché annuel de 10 mois reste un marché annuel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_marche', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->smallInteger('duree_mois')->nullable()
                ->comment('Durée de référence proposée à la création (12 pour annuel, 1 pour mensuel, vide pour pluriannuel)');
            $table->boolean('pluri_annuel')->default(false)
                ->comment('true = le marché s\'engage par tranches successives sur plusieurs exercices');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories_marche');
    }
};
