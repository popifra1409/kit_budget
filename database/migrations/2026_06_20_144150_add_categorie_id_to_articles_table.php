<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('categorie_id')->nullable()->after('categorie')
                ->constrained('categories_article')->nullOnDelete();
        });

        // ✅ Migration des données : créer une catégorie pour chaque valeur
        // texte distincte déjà utilisée, en marquant "pharmacie" si applicable
        $valeurs = DB::table('articles')
            ->whereNotNull('categorie')
            ->where('categorie', '!=', '')
            ->distinct()
            ->pluck('categorie');

        foreach ($valeurs as $libelle) {
            $id = DB::table('categories_article')->insertGetId([
                'libelle'       => $libelle,
                'est_pharmacie' => Str::lower($libelle) === 'pharmacie',
                'actif'         => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('articles')
                ->where('categorie', $libelle)
                ->update(['categorie_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categorie_id');
        });
    }
};
