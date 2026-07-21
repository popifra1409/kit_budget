<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mouvements_collectifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collectif_budgetaire_id')->constrained('collectifs_budgetaires')->cascadeOnDelete();
            $table->enum('type', ['depense', 'recette']);
            // Ligne existante modifiée
            $table->foreignId('ligne_depense_id')->nullable()->constrained('lignes_budgetaires')->nullOnDelete();
            $table->foreignId('ligne_recette_id')->nullable()->constrained('lignes_previsions_recettes')->nullOnDelete();
            // Nouvelle ligne créée par ce mouvement
            $table->foreignId('nouvelle_ligne_depense_id')->nullable()->constrained('lignes_budgetaires')->nullOnDelete();
            $table->foreignId('nouvelle_ligne_recette_id')->nullable()->constrained('lignes_previsions_recettes')->nullOnDelete();
            $table->decimal('montant_modification', 15, 2)->default(0);
            $table->string('motif')->nullable();
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE mouvements_collectifs
            ADD CONSTRAINT check_mouvement_ligne
            CHECK (
                (ligne_depense_id IS NOT NULL) OR
                (ligne_recette_id IS NOT NULL) OR
                (nouvelle_ligne_depense_id IS NOT NULL) OR
                (nouvelle_ligne_recette_id IS NOT NULL)
            )
        ');
    }

    public function down()
    {
        DB::statement('ALTER TABLE mouvements_collectifs DROP CONSTRAINT IF EXISTS check_mouvement_ligne');
        Schema::dropIfExists('mouvements_collectifs');
    }
};
