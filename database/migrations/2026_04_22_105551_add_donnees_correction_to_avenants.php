<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avenants', function (Blueprint $table) {
            $table->json('donnees_correction')->nullable()
                ->after('delta_montant')
                ->comment('Nouveaux champs à appliquer sur le document source');
        });
    }
    public function down(): void
    {
        Schema::table('avenants', function (Blueprint $table) {
            $table->dropColumn('donnees_correction');
        });
    }
};
