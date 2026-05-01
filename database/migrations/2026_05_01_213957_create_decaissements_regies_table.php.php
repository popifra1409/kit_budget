<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decaissements_regies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regie_avance_id')
                ->constrained('regies_avances')
                ->cascadeOnDelete();

            // ── Tranche ──────────────────────────────────
            $table->string('numero', 20)->unique();
            $table->unsignedTinyInteger('trimestre')->default(1); // 1,2,3,4 indicatif
            $table->string('libelle_tranche')->nullable(); // "Tranche T1 2025" etc.

            // ── Montants ─────────────────────────────────
            $table->decimal('montant_demande', 15, 2)->default(0);
            $table->decimal('montant_accorde', 15, 2)->default(0);

            // ── Dates ────────────────────────────────────
            $table->date('date_demande');
            $table->date('date_decaissement')->nullable();
            $table->date('date_apurement')->nullable();

            // ── Certificat ───────────────────────────────
            $table->string('certificat_numero')->nullable();
            $table->string('certificat_fichier')->nullable();

            // ── Statut ───────────────────────────────────
            $table->enum('statut', [
                'demande',   // en attente de validation
                'accorde',   // validé par agent comptable
                'verse',     // espèces versées
                'apure',     // compte d'emploi validé
            ])->default('demande');

            // ── Montants consommés sur cette tranche ──────
            $table->decimal('montant_depense',    15, 2)->default(0);
            $table->decimal('montant_ir_collecte', 15, 2)->default(0); 
            $table->decimal('montant_solde',      15, 2)->default(0); 

            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decaissements_regies');
    }
};
