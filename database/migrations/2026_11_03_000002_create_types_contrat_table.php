<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — paramétrage 2/5.
 *
 * « Type de contrat selon le seuil du montant : ≥ 50 millions → Marché,
 * < 50 millions → Lettre de commande » (scans du 27/10).
 *
 * Le seuil est une DONNÉE, pas du code : la convention du dépôt est déjà de
 * stocker les bornes dans le paramétrage (parametres_structure.seuil_achat_direct_regie,
 * seuil_bon_commande_regie), pour qu'elles changent sans déploiement.
 *
 * Chaque ligne est un test « montant (operateur) seuil_min ». TypeContrat::pourMontant()
 * les évalue dans l'ordre du champ « ordre » et retient la première qui réussit :
 * l'ordre fait donc autorité, deux lignes qui se chevauchent ne sont pas une erreur
 * de saisie mais un arbitrage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_contrat', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->decimal('seuil_min', 18, 2)->default(0);
            $table->enum('operateur', array_keys(config('marches.operateurs_seuil')))
                ->default('gte')
                ->comment('Sens du test sur le montant : gte = montant >= seuil_min, lt = montant < seuil_min');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_contrat');
    }
};
