<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('unite_mesure_id')->nullable()->after('unite_mesure')
                ->constrained('unites_mesure')->nullOnDelete();

            $table->foreignId('conditionnement_id')->nullable()->after('categorie')
                ->constrained('conditionnements')->nullOnDelete();
        });

        // ✅ Migration des données : créer une unité de mesure pour chaque
        // valeur texte distincte déjà utilisée, puis lier les articles
        $valeurs = DB::table('articles')
            ->whereNotNull('unite_mesure')
            ->where('unite_mesure', '!=', '')
            ->distinct()
            ->pluck('unite_mesure');

        foreach ($valeurs as $libelle) {
            $id = DB::table('unites_mesure')->insertGetId([
                'libelle'    => $libelle,
                'actif'      => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('articles')
                ->where('unite_mesure', $libelle)
                ->update(['unite_mesure_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unite_mesure_id');
            $table->dropConstrainedForeignId('conditionnement_id');
        });
    }
};
