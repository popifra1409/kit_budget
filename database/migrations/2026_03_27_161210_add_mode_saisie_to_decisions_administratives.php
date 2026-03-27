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
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->string('mode_saisie')->default('calcule')->after('autres_retenues');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            //
        });
    }
};
