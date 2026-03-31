<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('etat_configs', function (Blueprint $table) {
            // ✅ Type document — regroupe les variantes du même document
            // Ex: toutes les variantes d'OP ont type_document = 'ordonnance_paiement'
            $table->string('type_document')->nullable()->after('code')
                ->comment('Type de document — regroupe les variantes');

            // ✅ Variante par défaut pour ce type_document
            $table->boolean('est_defaut')->default(false)->after('type_document')
                ->comment('Variante utilisée par défaut pour ce type_document');

            $table->index('type_document');
            $table->index(['type_document', 'est_defaut']);
        });

        // ✅ Remplir type_document depuis les codes existants
        // (correspondance code → type_document)
        $mapping = [
            'certificat_engagement' => 'certificat_engagement',
            'bon_commande' => 'bon_commande',
            'autorisation_engagement' => 'autorisation_engagement',
            'bordereau_engagement' => 'bordereau_engagement',
            'memoire_depense' => 'memoire_depense',
            'decision_administrative' => 'decision_administrative',
            'ordonnance_paiement' => 'ordonnance_paiement',
            'ordonnance_paiement_impot' => 'ordonnance_paiement_impot',
            'decision_previsionnelle' => 'decision_previsionnelle',
            'pv_reception' => 'pv_reception',
            'ordre_entree' => 'ordre_entree',
            'bon_sortie_provisoire' => 'bon_sortie_provisoire',
            'ordre_sortie' => 'ordre_sortie',
            'fiche_detenteur' => 'fiche_detenteur',
        ];

        foreach ($mapping as $code => $typeDocument) {
            \DB::table('etat_configs')
                ->where('code', $code)
                ->update([
                    'type_document' => $typeDocument,
                    'est_defaut' => true, // Les états existants deviennent les défauts
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('etat_configs', function (Blueprint $table) {
            $table->dropIndex(['type_document']);
            $table->dropIndex(['type_document', 'est_defaut']);
            $table->dropColumn(['type_document', 'est_defaut']);
        });
    }
};