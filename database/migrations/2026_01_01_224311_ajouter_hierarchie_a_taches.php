<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taches', function (Blueprint $table) {
            // Ajouter parent_id pour la hiérarchie
            $table->foreignId('parent_id')->nullable()->after('activite_id')->constrained('taches')->onDelete('cascade');

            // Ajouter niveau (tache, sous_tache)
            $table->string('niveau')->default('tache')->after('parent_id');

            // Rendre nomenclature_id nullable (seulement pour les sous-tâches)
            $table->foreignId('nomenclature_id')->nullable()->change();

            // Index pour performance
            $table->index('parent_id');
            $table->index('niveau');
        });

        // Ajouter une contrainte CHECK pour le niveau (PostgreSQL)
        DB::statement("
            ALTER TABLE taches 
            ADD CONSTRAINT taches_niveau_check 
            CHECK (niveau IN ('tache', 'sous_tache'))
        ");
    }

    public function down(): void
    {
        // Supprimer la contrainte
        DB::statement('ALTER TABLE taches DROP CONSTRAINT IF EXISTS taches_niveau_check');

        Schema::table('taches', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'niveau']);
        });
    }
};
