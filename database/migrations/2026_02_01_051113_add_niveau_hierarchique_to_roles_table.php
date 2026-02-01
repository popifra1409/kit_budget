<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->integer('niveau_hierarchique')->default(0)->after('name')
                ->comment('0 = plus bas, 100 = plus haut');
        });

        // Définir les niveaux hiérarchiques
        DB::table('roles')->where('name', 'operateur_budget')->update(['niveau_hierarchique' => 10]);
        DB::table('roles')->where('name', 'operateur_recette')->update(['niveau_hierarchique' => 10]);
        DB::table('roles')->where('name', 'chef_service')->update(['niveau_hierarchique' => 30]);
        DB::table('roles')->where('name', 'sous_directeur')->update(['niveau_hierarchique' => 50]);
        DB::table('roles')->where('name', 'daaf')->update(['niveau_hierarchique' => 70]);
        DB::table('roles')->where('name', 'directeur')->update(['niveau_hierarchique' => 90]);
        DB::table('roles')->where('name', 'controleur_financier')->update(['niveau_hierarchique' => 60]);
        DB::table('roles')->where('name', 'agence_comptable')->update(['niveau_hierarchique' => 60]);
        DB::table('roles')->where('name', 'admin')->update(['niveau_hierarchique' => 95]);
        DB::table('roles')->where('name', 'super_admin')->update(['niveau_hierarchique' => 100]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('niveau_hierarchique');
        });
    }
};
