<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIQUIDATION : vérification de la dette et arrêt de son montant, entre l'engagement et
 * l'ordonnancement. Circuit : service fait (comptable matières) → liquidation (ordonnateur)
 * → visa de régularité (contrôleur financier, selon paramètre).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Référentiel paramétrable : nature du service fait et preuves attendues
        Schema::create('natures_service_fait', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('preuves_service_fait', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nature_service_fait_id')->constrained('natures_service_fait')->cascadeOnDelete();
            $table->string('libelle');
            $table->boolean('obligatoire')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('liquidations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->foreignId('engagement_id')->constrained('engagements')->restrictOnDelete();
            $table->foreignId('nature_service_fait_id')->nullable()->constrained('natures_service_fait')->nullOnDelete();
            $table->foreignId('reception_id')->nullable()->constrained('receptions')->nullOnDelete();

            $table->decimal('montant_liquide', 18, 2);
            $table->date('date_service_fait')->nullable();
            $table->text('observations')->nullable();

            // brouillon → service_fait_certifie → liquidee → visee  (rejet : retour en brouillon motivé)
            $table->string('statut', 30)->default('brouillon');

            $table->foreignId('certifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_certification')->nullable();
            $table->foreignId('liquide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_liquidation')->nullable();
            $table->foreignId('vise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_visa')->nullable();
            $table->text('motif_rejet')->nullable();

            // Échéance de paiement FIGÉE à la liquidation (délai en vigueur à cette date)
            $table->unsignedInteger('delai_paiement_jours')->nullable();
            $table->date('date_echeance_paiement')->nullable();

            $table->json('controles')->nullable();   // résultat des contrôles à la liquidation

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['engagement_id', 'statut']);
            $table->index('date_echeance_paiement');
        });

        Schema::create('liquidation_preuves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidation_id')->constrained('liquidations')->cascadeOnDelete();
            $table->foreignId('preuve_service_fait_id')->nullable()->constrained('preuves_service_fait')->nullOnDelete();
            $table->string('libelle');
            $table->boolean('obligatoire')->default(false);
            $table->boolean('fourni')->default(false);
            $table->string('reference_document')->nullable();   // ex. n° de bon de livraison, de PV
            $table->string('fichier')->nullable();
            $table->foreignId('ajoute_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidation_preuves');
        Schema::dropIfExists('liquidations');
        Schema::dropIfExists('preuves_service_fait');
        Schema::dropIfExists('natures_service_fait');
    }
};
