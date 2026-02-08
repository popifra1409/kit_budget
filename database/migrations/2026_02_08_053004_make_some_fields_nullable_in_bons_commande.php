<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            // Rendre nullable les champs qui peuvent être vides dans un brouillon
            $table->unsignedBigInteger('budget_id')->nullable()->change();
            $table->unsignedBigInteger('fournisseur_id')->nullable()->change();
            $table->unsignedBigInteger('service_demandeur_id')->nullable()->change();
            $table->date('date_livraison_prevue')->nullable()->change();
            $table->text('objet')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            // Remettre NOT NULL (attention : cela échouera s'il y a des NULL en base)
            $table->unsignedBigInteger('budget_id')->nullable(false)->change();
            $table->unsignedBigInteger('fournisseur_id')->nullable(false)->change();
            $table->unsignedBigInteger('service_demandeur_id')->nullable(false)->change();
            $table->date('date_livraison_prevue')->nullable(false)->change();
            $table->text('objet')->nullable(false)->change();
        });
    }
};
