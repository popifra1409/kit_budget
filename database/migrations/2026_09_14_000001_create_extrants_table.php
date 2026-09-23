<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();

            $table->string('libelle'); // ex: "Equipements installes et fonctionnels"
            $table->text('description')->nullable();

            // Quantite/qualite du bien ou service produit (Module 1, VI)
            $table->decimal('quantite_prevue', 15, 2)->nullable();
            $table->string('unite_mesure')->nullable(); // unites, %, nombre de lits, etc.
            $table->decimal('quantite_realisee', 15, 2)->nullable();

            $table->string('statut')->default('prevu'); // prevu, en_cours, realise, partiel, non_realise
            $table->date('date_realisation_prevue')->nullable();
            $table->date('date_realisation_effective')->nullable();

            $table->text('commentaire')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extrants');
    }
};
