<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_structure', function (Blueprint $table) {
            $table->id();

            // Informations principales
            $table->string('nom_structure')->comment('Nom complet');
            $table->string('sigle', 50)->nullable();
            $table->string('logo')->nullable();

            // Coordonnées
            $table->text('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('pays', 100)->default('Cameroun');
            $table->string('telephone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('site_web', 100)->nullable();
            $table->string('boite_postale', 50)->nullable();

            // Informations officielles
            $table->string('ministere_tutelle')->nullable();
            $table->string('numero_contribuable', 100)->nullable();
            $table->string('rccm', 100)->nullable();

            // En-tête bilingue
            $table->string('devise_gauche')->default('Paix – Travail - Patrie');
            $table->string('devise_droite')->default('Peace – Work - Fatherland');
            $table->string('pays_gauche')->default('REPUBLIQUE DU CAMEROUN');
            $table->string('pays_droite')->default('REPUBLIC OF CAMEROON');

            // Hiérarchie
            $table->string('direction_generale')->nullable();
            $table->string('direction_generale_en')->nullable();
            $table->string('sous_direction')->nullable();
            $table->string('sous_direction_en')->nullable();

            // Signatures
            $table->string('nom_ordonnateur')->nullable();
            $table->string('fonction_ordonnateur')->nullable();
            $table->string('nom_comptable')->nullable();
            $table->string('fonction_comptable')->nullable();

            // Paramètres
            $table->decimal('taux_tva_defaut', 5, 2)->default(19.25);
            $table->string('monnaie', 10)->default('FCFA');
            $table->boolean('actif')->default(true);
            $table->integer('exercice_courant')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_structure');
    }
};
