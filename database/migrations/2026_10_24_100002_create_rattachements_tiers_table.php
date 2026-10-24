<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Gestion Comptable et Traitement des Flux Financiers » — prérequis 3/3.
 *
 * Les tiers du budget existent déjà (fournisseurs, personnels, tiers_recettes) :
 * il ne faut pas les re-créer pour la comptabilité, seulement dire dans quel
 * compte de tiers SYSCOHADA chacun s'impute. D'où cette table de rattachement
 * polymorphe — la liaison, pas le doublon.
 *
 * Sans rattachement posé, RattachementTiers retombe sur le compte dont
 * comptes_comptables.type_tiers désigne le groupe (401 pour un fournisseur,
 * 421 pour une personne, 411 pour un tiers de recette) : la saisie automatisée
 * a toujours un compte à proposer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rattachements_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('tierable_type', 255);
            $table->unsignedBigInteger('tierable_id');
            $table->foreignId('compte_comptable_id')
                ->constrained('comptes_comptables', 'id')->restrictOnDelete();
            $table->string('compte_code', 20);
            $table->foreignId('rattache_par')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tierable_type', 'tierable_id']);
            $table->index('compte_comptable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rattachements_tiers');
    }
};
