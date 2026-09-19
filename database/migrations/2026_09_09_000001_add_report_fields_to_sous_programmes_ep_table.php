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
            $table->foreignId('responsable_id')->nullable()->after('cadre_institutionnel')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
            $table->dropColumn(['objectif', 'strategie', 'cadre_institutionnel']);
        });
    }
};
