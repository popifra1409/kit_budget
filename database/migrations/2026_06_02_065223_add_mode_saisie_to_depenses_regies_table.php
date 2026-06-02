<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses_regies', function (Blueprint $table) {
            $table->string('mode_saisie')->default('montant_nap')->after('type_depense');
        });
    }

    public function down(): void
    {
        Schema::table('depenses_regies', function (Blueprint $table) {
            $table->dropColumn('mode_saisie');
        });
    }
};
