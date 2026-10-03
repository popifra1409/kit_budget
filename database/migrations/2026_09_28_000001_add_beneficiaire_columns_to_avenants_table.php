<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avenant « changement de bénéficiaire » : conserve l'ancien et le nouveau bénéficiaire
 * (Fournisseur ou Personnel) et le dossier fournisseur ouvert pour le nouveau fournisseur.
 *
 * Aucune contrainte sur type_correction : la valeur 'beneficiaire' est acceptée telle quelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avenants', function (Blueprint $table) {
            $table->string('beneficiaire_original_type')->nullable()->after('nomenclature_corrigee_id');
            $table->unsignedBigInteger('beneficiaire_original_id')->nullable()->after('beneficiaire_original_type');
            $table->string('beneficiaire_corrige_type')->nullable()->after('beneficiaire_original_id');
            $table->unsignedBigInteger('beneficiaire_corrige_id')->nullable()->after('beneficiaire_corrige_type');
            $table->foreignId('dossier_fournisseur_cree_id')->nullable()->after('beneficiaire_corrige_id')
                ->constrained('dossiers_fournisseurs')->nullOnDelete();

            $table->index(['beneficiaire_original_type', 'beneficiaire_original_id'], 'avenants_benef_original_index');
            $table->index(['beneficiaire_corrige_type', 'beneficiaire_corrige_id'], 'avenants_benef_corrige_index');
        });
    }

    public function down(): void
    {
        Schema::table('avenants', function (Blueprint $table) {
            $table->dropIndex('avenants_benef_original_index');
            $table->dropIndex('avenants_benef_corrige_index');
            $table->dropConstrainedForeignId('dossier_fournisseur_cree_id');
            $table->dropColumn([
                'beneficiaire_original_type',
                'beneficiaire_original_id',
                'beneficiaire_corrige_type',
                'beneficiaire_corrige_id',
            ]);
        });
    }
};
