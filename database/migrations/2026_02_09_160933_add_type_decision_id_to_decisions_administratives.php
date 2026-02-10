<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // Ajouter la nouvelle colonne
            $table->foreignId('type_decision_id')
                ->nullable()
                ->after('numero')
                ->constrained('types_decision')
                ->nullOnDelete();

            // L'ancienne colonne type_decision peut être gardée temporairement
            // ou supprimée si vous migrez toutes les données
        });
    }

    public function down()
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropForeign(['type_decision_id']);
            $table->dropColumn('type_decision_id');
        });
    }
};
