<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regies_avances', function (Blueprint $table) {
            $table->id();

            // ── Identification ──────────────────────────────
            $table->string('numero', 20)->unique();
            $table->string('libelle');
            $table->enum('type', ['rav', 'menu_depense']);

            // ── Rattachements ───────────────────────────────
            $table->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $table->foreignId('budget_id')->constrained('budgets')->restrictOnDelete();
            $table->foreignId('responsable_id')->constrained('users')->restrictOnDelete();

            // ── DA source (approvisionnement initial) ───────
            $table->foreignId('decision_administrative_id')
                ->nullable()
                ->constrained('decisions_administratives')
                ->nullOnDelete();

            // ── Montants ────────────────────────────────────
            $table->decimal('montant_alloue',     15, 2)->default(0);
            $table->decimal('montant_decaisse',   15, 2)->default(0); // total cumulé décaissé
            $table->decimal('montant_depense',    15, 2)->default(0); // total cumulé dépensé
            $table->decimal('montant_disponible', 15, 2)->default(0); // alloué - dépensé

            // ── Statut ──────────────────────────────────────
            $table->enum('statut', ['actif', 'suspendu', 'cloture'])->default('actif');

            // ── Dates ───────────────────────────────────────
            $table->date('date_creation')->nullable();
            $table->date('date_cloture')->nullable();

            // ── Divers ──────────────────────────────────────
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regies_avances');
    }
};