<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_log')) {
            Schema::table('activity_log', function (Blueprint $table) {
                // La colonne causer_id existe déjà, ajoutons juste un index si besoin
                if (!Schema::hasColumn('activity_log', 'causer_type')) {
                    $table->index(['causer_type', 'causer_id']);
                }
            });
        }
    }

    public function down(): void
    {
        // Rien à faire
    }
};
