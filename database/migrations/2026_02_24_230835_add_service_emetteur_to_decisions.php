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
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // Ajouter seulement service_emetteur_id si la colonne n'existe pas déjà
            if (!Schema::hasColumn('decisions_administratives', 'service_emetteur_id')) {
                $table->foreignId('service_emetteur_id')
                    ->nullable()
                    ->after('observations')
                    ->constrained('services')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            if (Schema::hasColumn('decisions_administratives', 'service_emetteur_id')) {
                $table->dropForeign(['service_emetteur_id']);
                $table->dropColumn('service_emetteur_id');
            }
        });
    }
};
