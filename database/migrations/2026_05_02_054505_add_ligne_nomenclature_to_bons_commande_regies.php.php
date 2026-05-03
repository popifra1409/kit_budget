<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande_regies', function (Blueprint $table) {
            // Ligne régie dérivée qui sera débitée
            $table->foreignId('ligne_regie_avance_id')
                ->nullable()
                ->after('depense_regie_id')
                ->constrained('lignes_regies_avances')
                ->nullOnDelete();

            // Provision (tranche) rattachée
            $table->foreignId('provision_ligne_regie_id')
                ->nullable()
                ->after('ligne_regie_avance_id')
                ->constrained('provisions_lignes_regies')
                ->nullOnDelete();

            // Statut engagement BCR
            $table->boolean('engage')->default(false)->after('statut');
            $table->timestamp('date_engagement')->nullable()->after('engage');
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande_regies', function (Blueprint $table) {
            $table->dropForeign(['ligne_regie_avance_id']);
            $table->dropForeign(['provision_ligne_regie_id']);
            $table->dropColumn([
                'ligne_regie_avance_id',
                'provision_ligne_regie_id',
                'engage',
                'date_engagement',
            ]);
        });
    }
};
