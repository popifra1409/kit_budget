<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Détecter automatiquement le nom de la table depuis le modèle
        $tableName = (new \App\Models\BordereauEngagement())->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Utilisateur qui détient actuellement le bordereau
            if (!Schema::hasColumn($tableName, 'detenu_par_id')) {
                $table->foreignId('detenu_par_id')
                    ->nullable()
                    ->after('instance_destinataire')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // Date de réception par le détenteur actuel
            if (!Schema::hasColumn($tableName, 'date_reception')) {
                $table->timestamp('date_reception')
                    ->nullable()
                    ->after('detenu_par_id');
            }

            // Date de la dernière action
            if (!Schema::hasColumn($tableName, 'date_derniere_action')) {
                $table->timestamp('date_derniere_action')
                    ->nullable()
                    ->after('date_reception');
            }

            // Nombre de jours d'attente
            if (!Schema::hasColumn($tableName, 'jours_attente')) {
                $table->integer('jours_attente')
                    ->default(0)
                    ->after('date_derniere_action');
            }

            // Indicateur de priorité
            if (!Schema::hasColumn($tableName, 'priorite')) {
                $table->enum('priorite', ['normale', 'urgente', 'tres_urgente'])
                    ->default('normale')
                    ->after('jours_attente');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = (new \App\Models\BordereauEngagement())->getTable();

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Supprimer les colonnes dans l'ordre inverse
            if (Schema::hasColumn($tableName, 'priorite')) {
                $table->dropColumn('priorite');
            }

            if (Schema::hasColumn($tableName, 'jours_attente')) {
                $table->dropColumn('jours_attente');
            }

            if (Schema::hasColumn($tableName, 'date_derniere_action')) {
                $table->dropColumn('date_derniere_action');
            }

            if (Schema::hasColumn($tableName, 'date_reception')) {
                $table->dropColumn('date_reception');
            }

            if (Schema::hasColumn($tableName, 'detenu_par_id')) {
                $table->dropForeign(['detenu_par_id']);
                $table->dropColumn('detenu_par_id');
            }
        });
    }
};
