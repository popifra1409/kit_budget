<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clôture d'exercice : reports de crédits et annulations de fin de gestion.
 * Par ligne : dotation actualisée = payé + reporté + annulé.
 * Circuit : preparation → arretee (arrêté de l'ordonnateur) → avis_ca → reprise (étape 5b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clotures_exercice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $table->foreignId('budget_id')->constrained('budgets')->restrictOnDelete();
            $table->string('statut', 20)->default('preparation');

            // Arrêté de report de l'ordonnateur
            $table->string('reference_arrete')->nullable();
            $table->date('date_arrete')->nullable();
            $table->string('piece_arrete')->nullable();
            $table->foreignId('arrete_par')->nullable()->constrained('users')->nullOnDelete();

            // Avis conforme du conseil d'administration
            $table->string('avis_ca', 20)->nullable();             
            $table->string('reference_avis_ca')->nullable();
            $table->date('date_avis_ca')->nullable();
            $table->string('piece_avis_ca')->nullable();

            $table->boolean('report_fonctionnement_autorise')->default(false); 
            $table->timestamp('date_calcul')->nullable();
            $table->json('totaux')->nullable();
            $table->text('observations')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exercice_id', 'budget_id']);
        });

        Schema::create('clotures_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloture_exercice_id')->constrained('clotures_exercice')->cascadeOnDelete();
            $table->foreignId('ligne_budgetaire_id')->constrained('lignes_budgetaires')->restrictOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('libelle')->nullable();
            $table->unsignedTinyInteger('titre')->nullable();
            $table->foreignId('sous_programme_ep_id')->nullable()->constrained('sous_programmes_ep')->nullOnDelete();

            $table->decimal('dotation', 18, 2)->default(0);          
            $table->decimal('engage', 18, 2)->default(0);           
            $table->decimal('liquide', 18, 2)->default(0);
            $table->decimal('ordonnance', 18, 2)->default(0);
            $table->decimal('paye', 18, 2)->default(0);
            $table->decimal('engage_non_paye', 18, 2)->default(0);

            $table->decimal('report_propose', 18, 2)->default(0);
            $table->decimal('report_retenu', 18, 2)->default(0);    
            $table->decimal('annule', 18, 2)->default(0);            
            $table->string('motif_ecart')->nullable();                 

            $table->timestamps();
            $table->unique(['cloture_exercice_id', 'ligne_budgetaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clotures_lignes');
        Schema::dropIfExists('clotures_exercice');
    }
};
