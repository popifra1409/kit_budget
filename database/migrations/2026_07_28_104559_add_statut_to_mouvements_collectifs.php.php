<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->enum('statut', ['actif', 'annule'])
                ->default('actif')
                ->after('motif')
                ->comment('Permet d\'annuler/corriger UN mouvement précis sans toucher aux autres du même collectif');

            $table->timestamp('date_annulation')->nullable()->after('statut');
            $table->unsignedBigInteger('annule_par')->nullable()->after('date_annulation');

            $table->foreign('annule_par')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->dropForeign(['annule_par']);
            $table->dropColumn(['statut', 'date_annulation', 'annule_par']);
        });
    }
};
