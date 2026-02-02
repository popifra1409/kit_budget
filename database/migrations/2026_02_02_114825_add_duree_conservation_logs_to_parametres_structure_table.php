<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            $table->integer('duree_conservation_logs')
                ->default(365)
                ->comment('Durée de conservation des logs en jours');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            $table->dropColumn('duree_conservation_logs');
        });
    }
};
