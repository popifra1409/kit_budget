<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memoires_depense', function (Blueprint $table) {
            $table->string('mode_saisie')->default('montant_nap')->after('exercice');
        });
    }

    public function down(): void
    {
        Schema::table('memoires_depense', function (Blueprint $table) {
            $table->dropColumn('mode_saisie');
        });
    }
};
