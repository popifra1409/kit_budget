<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape B : le marché lui-même et son cycle de vie.
 *
 * « Information du marché : titre ou intitulé, code, montant. Caractéristique :
 * type de procédure. Type de contrat selon le seuil du montant. »
 * (scan du 27/10 17.20), suivi de « soumission du marché (département) →
 * réception des offres » (scan 04.50).
 *
 * Trois partis pris de structure :
 *
 * 1. Le classement marché / lettre de commande est une DONNÉE calculée depuis
 *    types_contrat (TypeContrat::pourMontant), mais l'humain peut le contredire :
 *    d'où classement_manuel + classement_justificatif + classement_par_id.
 *    La règle « on ne classe manuellement qu'en le justifiant » est tenue par la
 *    base, pas par le formulaire, parce que le formulaire n'est pas le seul
 *    chemin d'écriture.
 *
 * 2. Un marché en brouillon a le droit d'être incomplet (on saisit le titre et le
 *    montant estimatif avant d'avoir la catégorie ou le responsable). En
 *    revanche la base interdit qu'il QUITTE le brouillon sans être complet :
 *    c'est le CHECK marches_cycle_complet. Les actions du cycle préviennent
 *    avant, le CHECK arrête ce qui passerait par un autre chemin.
 *
 * 3. Pas de table d'historique des statuts : le dépôt journalise déjà chaque
 *    changement de colonne (AuditObserver) et chaque transition appelle
 *    ActivityLog::logAction avec ancien_statut / nouveau_statut. Une table
 *    dédiée n'aurait fait que dupliquer ce journal, déjà consultable à l'écran.
 *
 * Les étapes suivantes branchent leurs tables sur celle-ci sans la modifier :
 * offres (C), évaluations (D), ordres de service et fiche de présence (E),
 * engagement budgétaire via engagements.engageable_type et tranches (F).
 * commission_id est posé maintenant alors que la mise à disposition se fait en D :
 * une colonne en plus aujourd'hui évite une migration de plus en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marches', function (Blueprint $table) {
            $table->id();

            // — Information du marché (scan 17.20 I.1)
            $table->string('numero', 30)->unique()
                ->comment('Référence SIGB, générée par Marche::genererNumero() — MR-2026-0001');
            $table->string('titre')->comment('Intitulé du marché');
            $table->text('objet')->nullable()->comment('Description de l\'objet du marché');

            // — Rattachement
            $table->foreignId('exercice_id')->nullable()
                ->constrained('exercices')->cascadeOnDelete()
                ->comment('Exercice budgétaire, assigné automatiquement par HasExercice');
            $table->foreignId('expression_besoin_id')->nullable()
                ->constrained('expressions_besoins')->nullOnDelete()
                ->comment('Le « Besoin → DAO » du scan 17.20 : l\'expression de besoin d\'origine');
            $table->foreignId('service_demandeur_id')->nullable()
                ->constrained('services')->nullOnDelete()
                ->comment('Département / service qui a exprimé le besoin');
            $table->foreignId('responsable_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Responsable du marché côté service des marchés');

            // — Caractéristiques (scan 17.20 I.2 et I.3)
            $table->foreignId('categorie_id')->nullable()
                ->constrained('categories_marche')->restrictOnDelete()
                ->comment('pluriannuel / annuel / mensuel');
            $table->foreignId('type_procedure_id')->nullable()
                ->constrained('types_procedure')->restrictOnDelete();
            $table->foreignId('type_contrat_id')->nullable()
                ->constrained('types_contrat')->restrictOnDelete()
                ->comment('marché ou lettre de commande, selon le seuil du montant');
            $table->boolean('classement_manuel')->default(false)
                ->comment('true = le type de contrat a été choisi contre le seuil');
            $table->text('classement_justificatif')->nullable()
                ->comment('Obligatoire dès que classement_manuel = true');
            $table->foreignId('classement_par_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('classement_le')->nullable();

            $table->foreignId('nomenclature_id')->nullable()
                ->constrained('nomenclature_budgetaire')->nullOnDelete()
                ->comment('Ligne budgétaire principale, reprise à l\'engagement (étape F)');

            // — Montants et délais
            $table->decimal('montant_estime', 18, 2)->default(0)
                ->comment('Montant estimatif : c\'est lui qui déclenche la règle de seuil');
            $table->smallInteger('duree_execution_mois')->nullable()
                ->comment('Délai d\'exécution contractuel, pré-rempli depuis categories_marche.duree_mois');

            // — Dates du cycle
            $table->date('date_publication_dao')->nullable()
                ->comment('Publication de l\'avis de lancement');
            $table->date('date_limite_depot')->nullable()
                ->comment('Réception des offres : date limite de dépôt (scan 17.29)');
            $table->date('date_ouverture_plis')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->timestamp('date_annulation')->nullable();

            // — Cycle de vie
            $table->enum('statut', array_keys(config('marches.statuts_marche')))
                ->default('brouillon');
            $table->foreignId('valide_par_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('annule_par_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('motif_annulation')->nullable();

            // — Commission épinglée (utilisée par l'évaluation, étape D)
            $table->foreignId('commission_id')->nullable()
                ->constrained('commissions')->nullOnDelete()
                ->comment('Commission de département chargée de la procédure');

            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('statut');
            $table->index('exercice_id');
            $table->index('categorie_id');
            $table->index('type_procedure_id');
            $table->index('service_demandeur_id');
            $table->index('commission_id');
            $table->index(['statut', 'exercice_id']);
        });

        // Un classement manuel sans justification n'existe pas en base.
        DB::statement("
            ALTER TABLE marches
                ADD CONSTRAINT marches_classement_justifie_check
                CHECK (
                    classement_manuel = false
                    OR (classement_justificatif IS NOT NULL AND btrim(classement_justificatif) <> '')
                )
        ");

        // Le brouillon a le droit d'être incomplet, pas le reste du cycle.
        DB::statement("
            ALTER TABLE marches
                ADD CONSTRAINT marches_cycle_complet_check
                CHECK (
                    statut = 'brouillon'
                    OR (
                        categorie_id IS NOT NULL
                        AND type_procedure_id IS NOT NULL
                        AND type_contrat_id IS NOT NULL
                        AND responsable_id IS NOT NULL
                        AND exercice_id IS NOT NULL
                        AND montant_estime > 0
                    )
                )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('marches');
    }
};
