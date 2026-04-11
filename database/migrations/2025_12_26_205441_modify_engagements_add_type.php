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
        Schema::table('engagements', function (Blueprint $table) {
            // Ajouter le type d'engagement (extensible)
            $table->string('type_engagement', 100)->after('budget_id')
                ->comment('Type: BC, DA, Avance, Mission, Lettre-commande, Marché, etc.');

            // Rendre engageable optionnel (on peut avoir juste le type)
            $table->string('engageable_type')->nullable()->change();
            $table->unsignedBigInteger('engageable_id')->nullable()->change();

            // Nomenclature obligatoire (ligne budgétaire principale)
            $table->foreignId('nomenclature_principale_id')->nullable()->after('budget_id')
                ->constrained('nomenclature_budgetaire')->onDelete('restrict')
                ->comment('Nomenclature budgétaire principale de l\'engagement');

            // Référence du document source
            $table->string('reference_document')->nullable()->after('objet')
                ->comment('N° du document (BC-xxx, DA-xxx, Mission-xxx, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropForeign(['nomenclature_principale_id']);
            $table->dropColumn(['type_engagement', 'nomenclature_principale_id', 'reference_document']);
        });
    }
};
