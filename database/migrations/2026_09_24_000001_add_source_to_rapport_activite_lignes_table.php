<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapport_activite_lignes', function (Blueprint $table) {
            // null = jamais renseignee, 'manuelle' = saisie agent, 'budget' = actualisee depuis le module Budget
            $table->enum('source_realisation', ['manuelle', 'budget'])->nullable()->after('realisation');
            $table->foreignId('ligne_budgetaire_id')->nullable()->after('tache_id')
                ->constrained('lignes_budgetaires')->nullOnDelete();
            $table->decimal('quote_part', 7, 4)->nullable()->after('source_realisation'); // prorata si ligne partagee
            $table->timestamp('realisation_actualisee_le')->nullable()->after('quote_part');
        });
    }

    public function down(): void
    {
        Schema::table('rapport_activite_lignes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ligne_budgetaire_id');
            $table->dropColumn(['source_realisation', 'quote_part', 'realisation_actualisee_le']);
        });
    }
};
