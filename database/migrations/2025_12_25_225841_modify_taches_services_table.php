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
        Schema::table('taches', function (Blueprint $table) {
            // Supprimer l'ancienne colonne string
            $table->dropColumn('service_responsable');

            // Ajouter la foreign key vers services
            $table->foreignId('service_id')->nullable()->after('guichet')
                ->constrained('services')->onDelete('set null')
                ->comment('Service responsable de la tâche');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taches', function (Blueprint $table) {
            // Supprimer la foreign key
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');

            // Remettre l'ancienne colonne
            $table->string('service_responsable')->nullable();
        });
    }
};
