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
        // TABLE DECISIONS ADMINISTRATIVES (Pour personnel)
        Schema::create('decisions_administratives', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique()->comment('Numéro décision (DA-YY-XXXXX)');
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('restrict');

            // Personnel concerné (peut être un User ou une table personnel externe)
            $table->foreignId('personnel_id')->nullable()->constrained('users')->onDelete('restrict')
                ->comment('Personnel bénéficiaire');
            $table->string('nom_personnel')->nullable()->comment('Nom si pas dans users');
            $table->string('matricule')->nullable()->comment('Matricule du personnel');
            $table->string('fonction')->nullable()->comment('Fonction du personnel');

            // Type de décision
            $table->enum('type_decision', [
                'avancement',
                'promotion',
                'prime',
                'indemnite',
                'formation',
                'mission',
                'affectation',
                'autre'
            ])->comment('Type de décision administrative');

            // Dates
            $table->date('date_decision')->comment('Date de la décision');
            $table->date('date_effet')->nullable()->comment('Date de prise d\'effet');
            $table->date('date_fin')->nullable()->comment('Date de fin (pour missions, formations)');

            // Objet et montant
            $table->text('objet')->comment('Objet de la décision');
            $table->decimal('montant_brut', 15, 2)->default(0)->comment('Montant brut');
            $table->decimal('cnps', 15, 2)->default(0)->comment('CNPS (cotisation)');
            $table->decimal('ir', 15, 2)->default(0)->comment('Impôt sur le revenu');
            $table->decimal('autres_retenues', 15, 2)->default(0)->comment('Autres retenues');
            $table->decimal('montant_net', 15, 2)->default(0)->comment('Montant net à payer');

            // Références
            $table->string('reference_decision')->nullable()->comment('N° arrêté, note de service, etc.');
            $table->string('signataire')->nullable()->comment('Signataire de la décision');

            // Statut
            $table->enum('statut', [
                'brouillon',
                'validee',
                'engagee',
                'ordonnancee',
                'liquidee',
                'payee',
                'annulee'
            ])->default('brouillon');

            // Validation et engagement
            $table->foreignId('validee_par')->nullable()->constrained('users');
            $table->timestamp('date_validation')->nullable();

            $table->boolean('engagee')->default(false);
            $table->decimal('montant_engage', 15, 2)->default(0);
            $table->timestamp('date_engagement')->nullable();

            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('numero');
            $table->index('budget_id');
            $table->index('personnel_id');
            $table->index('type_decision');
            $table->index('statut');
            $table->index('date_decision');
        });

        DB::statement("COMMENT ON TABLE decisions_administratives IS 'Décisions administratives concernant le personnel'");

        // Contraintes
        DB::statement("ALTER TABLE decisions_administratives ADD CONSTRAINT check_montants_decision CHECK (
            montant_brut >= 0 AND
            cnps >= 0 AND
            ir >= 0 AND
            autres_retenues >= 0 AND
            montant_net >= 0 AND
            montant_engage >= 0
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decisions_administratives');
    }
};
