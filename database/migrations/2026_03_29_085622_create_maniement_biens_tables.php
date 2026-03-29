<?php
// ============================================================
// MIGRATION 1 : bons_sortie_fournitures
// BSF — Demande du service utilisateur
// ============================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Bon de Sortie de Fournitures (BSF) ───────────────
        // Produit et signé par le demandeur (service utilisateur)
        Schema::create('bons_sortie_fournitures', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();              // BSF-2026-0001
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->string('service_demandeur');
            $table->foreignId('demandeur_id')->constrained('users');
            $table->date('date_demande');
            $table->text('motif')->nullable();
            $table->text('observations')->nullable();
            $table->enum('statut', [
                'brouillon',
                'soumis',      // Soumis au magasin
                'traite',      // BSP créé
                'rejete',
                'annule',
            ])->default('brouillon');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'exercice_id']);
        });

        // Lignes du BSF
        Schema::create('lignes_bon_sortie_fournitures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_sortie_fourniture_id')
                ->constrained('bons_sortie_fournitures')
                ->onDelete('cascade');
            $table->foreignId('article_id')->constrained();
            $table->integer('quantite_demandee');
            $table->integer('quantite_accordee')->default(0); // Renseigné par comptable
            $table->text('observations')->nullable();
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

        // ── Bon de Sortie Provisoire (BSP) ───────────────────
        // Co-signé : demandeur + comptable-matières + ordonnateur
        Schema::create('bons_sortie_provisoires', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();              // BSP-2026-0001
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('bon_sortie_fourniture_id')
                ->nullable()
                ->constrained('bons_sortie_fournitures');
            $table->string('service_demandeur');
            $table->foreignId('demandeur_id')->constrained('users');
            $table->foreignId('comptable_matieres_id')->constrained('users');
            $table->foreignId('ordonnateur_id')->constrained('users');
            $table->date('date_bsp');
            $table->text('motif')->nullable();
            $table->text('observations')->nullable();

            // 3 signatures obligatoires
            $table->boolean('signe_demandeur')->default(false);
            $table->boolean('signe_comptable')->default(false);
            $table->boolean('signe_ordonnateur')->default(false);
            $table->date('date_signature')->nullable();

            $table->enum('statut', [
                'brouillon',
                'en_attente_signature',
                'signe',       // 3 signatures OK
                'execute',     // Sortie effectuée
                'annule',
            ])->default('brouillon');

            $table->foreignId('ordre_sortie_id')->nullable(); // OS lié
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'exercice_id']);
        });

        // Lignes du BSP
        Schema::create('lignes_bon_sortie_provisoires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_sortie_provisoire_id')
                ->constrained('bons_sortie_provisoires')
                ->onDelete('cascade');
            $table->foreignId('article_id')->constrained();
            $table->integer('quantite_autorisee');
            $table->integer('quantite_sortie')->default(0); // Réelle à l'exécution
            $table->decimal('prix_unitaire', 15, 2)->default(0);
            $table->decimal('valeur_totale', 15, 2)->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        // ── Ordre de Sortie (OS) ──────────────────────────────
        // Co-signé : comptable-matières + ordonnateur
        // Peut être journalier, hebdomadaire ou mensuel
        Schema::create('ordres_sortie', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();              // OS-2026-0001
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('comptable_matieres_id')->constrained('users');
            $table->foreignId('ordonnateur_id')->constrained('users');
            $table->date('date_os');
            $table->date('date_debut_periode')->nullable();  // Période couverte
            $table->date('date_fin_periode')->nullable();
            $table->enum('periodicite', ['journalier', 'hebdomadaire', 'mensuel'])
                ->default('journalier');
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->text('observations')->nullable();

            // Signatures
            $table->boolean('signe_comptable')->default(false);
            $table->boolean('signe_ordonnateur')->default(false);
            $table->date('date_signature')->nullable();

            $table->enum('statut', [
                'brouillon',
                'signe',
                'transmis',   // Envoyé au service budget
                'annule',
            ])->default('brouillon');

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'exercice_id']);
        });

        // Lignes OS — regroupe plusieurs BSP
        Schema::create('lignes_ordre_sortie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordre_sortie_id')
                ->constrained('ordres_sortie')
                ->onDelete('cascade');
            $table->foreignId('bon_sortie_provisoire_id')
                ->constrained('bons_sortie_provisoires');
            $table->foreignId('article_id')->constrained();
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 15, 2)->default(0);
            $table->decimal('valeur_totale', 15, 2)->default(0);
            $table->string('service_beneficiaire')->nullable();
            $table->timestamps();
        });

        // ── Fiche de Détenteur ────────────────────────────────
        // Suivi d'un bien durable affecté à une personne
        Schema::create('fiches_detenteurs', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();              // FD-2026-0001
            $table->foreignId('article_id')->constrained();
            $table->foreignId('detenteur_id')->constrained('users'); // Détenteur effectif
            $table->string('service_detenteur');
            $table->date('date_affectation');
            $table->date('date_retour')->nullable();
            $table->string('etat_affectation')->default('bon'); // bon, use, degrade, hors_service
            $table->string('etat_retour')->nullable();
            $table->text('observations_affectation')->nullable();
            $table->text('observations_retour')->nullable();
            $table->string('numero_serie')->nullable();
            $table->string('numero_inventaire')->nullable();      // N° estampillé
            $table->boolean('retourne')->default(false);
            $table->foreignId('comptable_matieres_id')->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['article_id', 'detenteur_id']);
            $table->index('retourne');
        });

        // ── Registre de Contrôle de Consommation ─────────────
        // Tenu par comptable, signé par demandeur
        Schema::create('registres_consommation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('article_id')->constrained();
            $table->foreignId('bon_sortie_provisoire_id')
                ->constrained('bons_sortie_provisoires');
            $table->foreignId('demandeur_id')->constrained('users');
            $table->string('service_demandeur');
            $table->date('date_sortie');
            $table->integer('quantite_sortie');
            $table->decimal('valeur_sortie', 15, 2)->default(0);
            $table->boolean('signe_demandeur')->default(false);
            $table->date('date_signature_demandeur')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['article_id', 'exercice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registres_consommation');
        Schema::dropIfExists('fiches_detenteurs');
        Schema::dropIfExists('lignes_ordre_sortie');
        Schema::dropIfExists('ordres_sortie');
        Schema::dropIfExists('lignes_bon_sortie_provisoires');
        Schema::dropIfExists('bons_sortie_provisoires');
        Schema::dropIfExists('lignes_bon_sortie_fournitures');
        Schema::dropIfExists('bons_sortie_fournitures');
    }
};
