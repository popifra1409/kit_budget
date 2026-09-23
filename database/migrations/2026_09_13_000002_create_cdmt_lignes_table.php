<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cdmt_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cdmt_exercice_id')->constrained('cdmt_exercices')->cascadeOnDelete();

            // Rattachement stratégique -> budgétaire (chaîne SousProgrammeEp -> Action -> Activite -> Tache)
            $table->foreignId('sous_programme_ep_id')->constrained('sous_programmes_ep')->restrictOnDelete();
            $table->foreignId('action_id')->nullable()->constrained('actions')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();

            $table->string('libelle'); // libelle de l'activite/projet programme

            // Ligne de Reference vs Mesure Nouvelle (Etape 2 du guide)
            $table->enum('nature', ['LR', 'MN']);

            // Maturite (uniquement pertinent pour les projets d'investissement, III du guide)
            $table->string('maturite')->nullable(); // etudes, DAO, en_cours, mature, non_requise

            $table->decimal('avant_n_moins_1', 18, 2)->default(0); // execution cumulee avant la periode
            $table->decimal('n_ae', 18, 2)->default(0);
            $table->decimal('n_cp', 18, 2)->default(0);

            $table->decimal('n_plus_1_ae', 18, 2)->default(0);
            $table->decimal('n_plus_1_cp', 18, 2)->default(0);
            $table->decimal('n_plus_2_ae', 18, 2)->default(0);
            $table->decimal('n_plus_2_cp', 18, 2)->default(0);
            $table->decimal('n_plus_3_ae', 18, 2)->default(0);
            $table->decimal('n_plus_3_cp', 18, 2)->default(0);

            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cdmt_lignes');
    }
};
