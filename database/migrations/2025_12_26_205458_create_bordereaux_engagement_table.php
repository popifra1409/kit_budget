<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // TABLE BORDEREAUX D'ENGAGEMENT
        Schema::create('bordereaux_engagement', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique()->comment('Numéro bordereau (BDE-YY-XXXXX)');
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('restrict');

            // Dates
            $table->date('date_emission')->comment('Date d\'émission du bordereau');
            $table->date('date_transmission')->nullable()->comment('Date de transmission');
            $table->integer('exercice')->comment('Exercice budgétaire');

            // Émetteur et destinataire
            $table->foreignId('emis_par')->constrained('users')->comment('Émis par (agent comptable, DAF...)');
            $table->string('instance_destinataire')->nullable()
                ->comment('Instance destinataire (Tutelle, Contrôle financier, etc.)');

            // Objet
            $table->text('objet')->comment('Objet du bordereau');

            // Montants totaux
            $table->decimal('montant_total', 15, 2)->default(0)
                ->comment('Montant total des engagements');
            $table->integer('nombre_engagements')->default(0)
                ->comment('Nombre d\'engagements dans le bordereau');

            // Statut et workflow
            $table->enum('statut', [
                'brouillon',
                'transmis',
                'en_cours',
                'valide',
                'rejete_partiel',
                'rejete_total',
                'retourne'
            ])->default('brouillon')->comment('Statut du bordereau');

            // Traçabilité de transmission
            $table->foreignId('receptionne_par')->nullable()->constrained('users')
                ->comment('Réceptionné par');
            $table->timestamp('date_reception')->nullable();

            $table->foreignId('valide_par')->nullable()->constrained('users')
                ->comment('Validé par');
            $table->timestamp('date_validation')->nullable();

            // Rejet
            $table->text('motif_rejet')->nullable();
            $table->foreignId('rejete_par')->nullable()->constrained('users');
            $table->timestamp('date_rejet')->nullable();

            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('numero');
            $table->index('budget_id');
            $table->index('exercice');
            $table->index('statut');
            $table->index('date_emission');
        });

        // TABLE DE LIAISON BORDEREAU <-> ENGAGEMENTS (Many-to-Many)
        Schema::create('bordereau_engagement_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bordereau_id')->constrained('bordereaux_engagement')->onDelete('cascade');
            $table->foreignId('engagement_id')->constrained('engagements')->onDelete('cascade');

            $table->integer('numero_ligne')->default(1)->comment('Ordre dans le bordereau');

            // Statut spécifique de cet engagement dans ce bordereau
            $table->enum('statut_ligne', [
                'en_attente',
                'valide',
                'rejete',
                'annule'
            ])->default('en_attente')->comment('Statut de cette ligne');

            $table->text('motif_rejet')->nullable()->comment('Motif si rejeté');
            $table->text('observations')->nullable();

            $table->timestamps();

            // Un engagement ne peut être que dans un seul bordereau actif
            $table->unique(['bordereau_id', 'engagement_id']);
            $table->index('bordereau_id');
            $table->index('engagement_id');
        });

        // TABLE HISTORIQUE DES MOUVEMENTS DE BORDEREAU
        Schema::create('bordereau_mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bordereau_id')->constrained('bordereaux_engagement')->onDelete('cascade');

            $table->string('action')->comment('transmis, reçu, validé, rejeté, retourné');
            $table->foreignId('effectue_par')->constrained('users');
            $table->timestamp('date_action');

            $table->string('de')->nullable()->comment('Provenance');
            $table->string('vers')->nullable()->comment('Destination');

            $table->text('commentaire')->nullable();

            $table->timestamps();

            $table->index('bordereau_id');
            $table->index('date_action');
        });

        DB::statement("COMMENT ON TABLE bordereaux_engagement IS 'Bordereaux de transmission d''engagements'");
        DB::statement("COMMENT ON TABLE bordereau_engagement_lignes IS 'Engagements attachés aux bordereaux'");
        DB::statement("COMMENT ON TABLE bordereau_mouvements IS 'Historique des mouvements de bordereaux'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bordereau_mouvements');
        Schema::dropIfExists('bordereau_engagement_lignes');
        Schema::dropIfExists('bordereaux_engagement');
    }
};
