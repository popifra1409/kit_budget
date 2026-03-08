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
        // Vérifier si la colonne n'existe pas déjà
        if (!Schema::hasColumn('memoires_depense', 'decision_administrative_id')) {
            Schema::table('memoires_depense', function (Blueprint $table) {
                $table->foreignId('decision_administrative_id')
                    ->nullable()
                    ->after('statut')
                    ->constrained('decisions_administratives')
                    ->nullOnDelete();
                
                $table->index('decision_administrative_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('memoires_depense', 'decision_administrative_id')) {
            Schema::table('memoires_depense', function (Blueprint $table) {
                $table->dropForeign(['decision_administrative_id']);
                $table->dropColumn('decision_administrative_id');
            });
        }
    }
};
