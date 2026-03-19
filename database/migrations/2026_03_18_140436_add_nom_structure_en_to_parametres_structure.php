<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            // Ajouter le champ nom_structure_en après nom_structure
            $table->string('nom_structure_en', 255)
                ->nullable()
                ->after('nom_structure')
                ->comment('Nom de la structure en anglais');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            $table->dropColumn('nom_structure_en');
        });
    }
};
