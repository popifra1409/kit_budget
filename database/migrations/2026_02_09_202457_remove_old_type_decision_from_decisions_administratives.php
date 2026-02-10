<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // Supprimer l'ancienne colonne
            $table->dropColumn('type_decision');
        });
    }

    public function down()
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->string('type_decision')->nullable()->after('type_decision_id');
        });
    }
};
