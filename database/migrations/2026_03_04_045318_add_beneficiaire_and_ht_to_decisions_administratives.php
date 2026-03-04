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
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // Type de bénéficiaire : personnel (personne physique) ou fournisseur (personne morale)
            $table->enum('type_beneficiaire', ['personnel', 'fournisseur'])
                ->default('personnel')
                ->after('personnel_id')
                ->comment('Type de bénéficiaire : personnel ou fournisseur');

            // ID du fournisseur (si type_beneficiaire = 'fournisseur')
            $table->foreignId('fournisseur_id')
                ->nullable()
                ->after('type_beneficiaire')
                ->constrained('fournisseurs')
                ->nullOnDelete()
                ->comment('Référence au fournisseur si type_beneficiaire = fournisseur');

            // Montant HT (déduit du montant brut/TTC)
            $table->decimal('montant_ht', 15, 2)
                ->default(0)
                ->after('montant_brut')
                ->comment('Montant HT calculé depuis le montant brut (TTC)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // Supprimer la contrainte de clé étrangère d'abord
            $table->dropForeign(['fournisseur_id']);

            // Puis supprimer les colonnes
            $table->dropColumn([
                'type_beneficiaire',
                'fournisseur_id',
                'montant_ht'
            ]);
        });
    }
};
