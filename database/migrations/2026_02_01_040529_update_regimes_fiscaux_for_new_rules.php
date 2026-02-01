<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancien régime IGS et garder seulement Réel et Simplifié
        DB::table('regimes_fiscaux')->where('code', 'IGS')->delete();
        DB::table('regimes_fiscaux')->where('code', 'EXONERE')->delete();

        // Mettre à jour les codes
        DB::table('regimes_fiscaux')
            ->where('code', 'REEL')
            ->update([
                'libelle' => 'Régime Réel',
                'description' => 'Régime réel d\'imposition pour CA ≥ 50 millions FCFA',
                'ca_min' => 50000000,
            ]);
    }

    public function down(): void
    {
        // Optionnel
    }
};
