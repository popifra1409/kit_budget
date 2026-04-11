<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table) {
            // Ajouter parent_id pour la hiérarchie
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('programmes')->onDelete('cascade');

            // Ajouter niveau (programme, sous_programme)
            $table->string('niveau')->default('programme')->after('parent_id');

            // Index pour performance
            $table->index('parent_id');
            $table->index('niveau');
        });

        // Ajouter une contrainte CHECK pour le niveau (PostgreSQL)
        DB::statement("
            ALTER TABLE programmes 
            ADD CONSTRAINT programmes_niveau_check 
            CHECK (niveau IN ('programme', 'sous_programme'))
        ");
    }

    public function down(): void
    {
        // Supprimer la contrainte
        DB::statement('ALTER TABLE programmes DROP CONSTRAINT IF EXISTS programmes_niveau_check');

        Schema::table('programmes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'niveau']);
        });
    }
};
