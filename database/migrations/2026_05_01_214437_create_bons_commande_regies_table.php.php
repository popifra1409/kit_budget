<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bons_commande_regies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regie_avance_id')
                ->constrained('regies_avances')
                ->cascadeOnDelete();

            $table->foreignId('depense_regie_id')
                ->nullable()
                ->constrained('depenses_regies')
                ->nullOnDelete();

            // ── Identification ───────────────────────────
            // BCR25-00001 pour RAV, BCM25-00001 pour Menu Dépense
            $table->string('numero', 20)->unique();
            $table->date('date_emission');
            $table->string('objet');

            // ── Fournisseur ──────────────────────────────
            $table->foreignId('fournisseur_id')
                ->constrained('fournisseurs')
                ->restrictOnDelete();

            // ── Montants totaux ──────────────────────────
            $table->decimal('montant_ht',  15, 2)->default(0);
            $table->decimal('montant_tva', 15, 2)->default(0);
            $table->decimal('montant_ttc', 15, 2)->default(0);
            $table->decimal('montant_ir',  15, 2)->default(0);
            $table->decimal('net_a_payer', 15, 2)->default(0);

            // ── Statut ───────────────────────────────────
            $table->enum('statut', [
                'brouillon',
                'valide',
                'livre_partiellement',
                'livre',
                'paye',
                'annule',
            ])->default('brouillon');

            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bons_commande_regies');
    }
};
