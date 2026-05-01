<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses_regies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regie_avance_id')
                ->constrained('regies_avances')
                ->cascadeOnDelete();

            $table->foreignId('decaissement_regie_id')
                ->nullable()
                ->constrained('decaissements_regies')
                ->nullOnDelete();

            $table->foreignId('ligne_regie_avance_id')
                ->constrained('lignes_regies_avances')
                ->restrictOnDelete();

            // ── Identification ───────────────────────────
            $table->string('numero', 20)->unique();
            $table->date('date_depense');
            $table->string('objet');

            // ── Type de dépense ──────────────────────────
            $table->enum('type_depense', ['achat_direct', 'bon_commande']);

            // ── Fournisseur ──────────────────────────────
            $table->foreignId('fournisseur_id')
                ->nullable()
                ->constrained('fournisseurs')
                ->nullOnDelete();
            $table->string('fournisseur_libre')->nullable(); // si non référencé

            // ── Montants ─────────────────────────────────
            $table->decimal('montant_ht',  15, 2)->default(0);
            $table->decimal('taux_tva',     5, 2)->default(19.25);
            $table->decimal('montant_tva', 15, 2)->default(0);
            $table->decimal('montant_ttc', 15, 2)->default(0);
            $table->decimal('taux_ir',      5, 2)->default(0);
            $table->decimal('montant_ir',  15, 2)->default(0);
            $table->decimal('net_a_payer', 15, 2)->default(0);

            // ── Statut ───────────────────────────────────
            $table->enum('statut', [
                'brouillon',
                'valide',
                'paye',
                'annule',
            ])->default('brouillon');

            // ── Justificatif ─────────────────────────────
            $table->string('justificatif_fichier')->nullable();
            $table->text('observations')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses_regies');
    }
};
