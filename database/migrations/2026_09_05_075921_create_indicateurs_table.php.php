<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicateurs', function (Blueprint $table) {
            $table->id();
            $table->string('indicateurable_type'); // 'sous_programme_ep' ou 'action_sous_programme' (morph map)
            $table->unsignedBigInteger('indicateurable_id');
            $table->index(['indicateurable_type', 'indicateurable_id']);

            $table->string('code')->unique();
            $table->string('libelle');
            $table->string('objectif_associe')->nullable(); // ce que l'indicateur mesure, en texte libre
            $table->string('unite_mesure')->nullable(); // %, nombre, FCFA, jours...
            $table->string('mode_calcul')->nullable();
            $table->string('periodicite_mesure')->default('annuelle'); // annuelle, semestrielle, trimestrielle

            $table->decimal('valeur_reference', 15, 2)->nullable();
            $table->unsignedSmallInteger('annee_reference')->nullable();
            $table->decimal('valeur_cible', 15, 2)->nullable();
            $table->unsignedSmallInteger('annee_cible')->nullable();

            $table->string('statut')->default('brouillon'); // brouillon, valide
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateurs');
    }
};
