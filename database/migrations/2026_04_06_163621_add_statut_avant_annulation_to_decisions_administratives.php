<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->string('statut_avant_annulation')
                ->nullable()
                ->after('statut')
                ->comment('Statut sauvegardé avant annulation — utilisé pour la récupération');
        });
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropColumn('statut_avant_annulation');
        });
    }
};