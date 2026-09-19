<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->text('objectif')->nullable()->after('description');
            $table->text('strategie')->nullable()->after('objectif');
            $table->text('cadre_institutionnel')->nullable()->after('strategie');
            // responsable_id existe deja depuis la migration initiale (Etape 2) - non redemande ici
        });
    }

    public function down(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->dropColumn(['objectif', 'strategie', 'cadre_institutionnel']);
        });
    }
};
