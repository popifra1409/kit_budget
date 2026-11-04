<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — paramétrage 4/5.
 *
 * « Élément d'évaluation du DAO : pièce administrative (étape 1, étape 2…),
 * pièce financière, description technique » (scans du 27/10).
 *
 * Structure PLATE : un volet (famille) + des lignes ordonnées, ce qui correspond
 * exactement aux « étapes » du schéma. Un parent_id n'apporterait qu'un niveau de
 * plus que personne n'a demandé, et compliquerait la grille d'évaluation de l'étape D.
 *
 * bareme_max est la note maximale de la ligne ; le coefficient pondérateur n'est pas
 * prévu tant que la grille d'évaluation n'existe pas (étape D), pour ne pas paramétrer
 * un calcul qui n'est encore écrit nulle part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elements_evaluation_dao', function (Blueprint $table) {
            $table->id();
            $table->enum('famille', array_keys(config('marches.familles_evaluation')))
                ->comment('Volet du DAO : administrative | financiere | technique');
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->decimal('bareme_max', 6, 2)->default(20)
                ->comment('Note maximale attribuable à cette étape d\'évaluation');
            $table->boolean('obligatoire')->default(true);
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['famille', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elements_evaluation_dao');
    }
};
