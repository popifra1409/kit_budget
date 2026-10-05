<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les virements budgétaires deviennent des MOUVEMENTS DE CRÉDITS typés
 * (fongibilité, virement, transfert), avec le cycle avant / pendant / après :
 * motif, analyse, contrôle de plafond figé, décideur, acte formel, impact sur la performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virements_budgetaires', function (Blueprint $table) {
            // Nature du mouvement
            $table->string('type_mouvement', 20)->nullable()->after('numero');   // fongibilite | virement | transfert
            $table->string('origine', 20)->default('gestion')->after('type_mouvement'); // gestion | collectif
            $table->foreignId('sous_programme_source_id')->nullable()->after('ligne_destination_id')
                ->constrained('sous_programmes_ep')->nullOnDelete();
            $table->foreignId('sous_programme_destination_id')->nullable()->after('sous_programme_source_id')
                ->constrained('sous_programmes_ep')->nullOnDelete();

            // AVANT : pourquoi, analyse, impact
            $table->string('categorie_motif', 40)->nullable()->after('motif');
            $table->text('analyse_ecart')->nullable()->after('categorie_motif');
            $table->boolean('impact_performance_verifie')->default(false);
            $table->text('impact_commentaire')->nullable();

            // PENDANT : décision et acte formel (reference_decision existe déjà)
            $table->string('decideur', 40)->nullable();
            $table->date('date_acte')->nullable();
            $table->string('piece_acte')->nullable();

            // APRÈS : contrôle de plafond figé à l'approbation, intégration au collectif
            $table->json('controle_plafond')->nullable();
            $table->boolean('integrer_collectif')->default(false);

            $table->index(['budget_id', 'type_mouvement', 'statut']);
        });

        // Les virements issus d'un collectif budgétaire (adopté par le CA) ne sont pas des
        // virements de gestion : ils ne comptent pas dans le plafond annuel.
        if (Schema::hasColumn('mouvements_collectifs', 'virement_budgetaire_id')) {
            DB::table('virements_budgetaires')
                ->whereIn('id', DB::table('mouvements_collectifs')->whereNotNull('virement_budgetaire_id')->pluck('virement_budgetaire_id'))
                ->update(['origine' => 'collectif']);
        }
    }

    public function down(): void
    {
        Schema::table('virements_budgetaires', function (Blueprint $table) {
            $table->dropIndex(['budget_id', 'type_mouvement', 'statut']);
            $table->dropConstrainedForeignId('sous_programme_source_id');
            $table->dropConstrainedForeignId('sous_programme_destination_id');
            $table->dropColumn([
                'type_mouvement',
                'origine',
                'categorie_motif',
                'analyse_ecart',
                'impact_performance_verifie',
                'impact_commentaire',
                'decideur',
                'date_acte',
                'piece_acte',
                'controle_plafond',
                'integrer_collectif',
            ]);
        });
    }
};
