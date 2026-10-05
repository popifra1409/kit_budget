<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache les ordonnances de paiement à la liquidation dont elles procèdent,
 * avec l'échéance de paiement FIGÉE à la liquidation (compte à rebours, arriérés).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->foreignId('liquidation_id')->nullable()->after('engagement_id')
                ->constrained('liquidations')->nullOnDelete();
            $table->unsignedInteger('delai_paiement_jours')->nullable()->after('liquidation_id');
            $table->date('date_echeance_paiement')->nullable()->after('delai_paiement_jours');

            $table->index('date_echeance_paiement');
        });
    }

    public function down(): void
    {
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->dropIndex(['date_echeance_paiement']);
            $table->dropConstrainedForeignId('liquidation_id');
            $table->dropColumn(['delai_paiement_jours', 'date_echeance_paiement']);
        });
    }
};
