<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transmissions', function (Blueprint $table) {
            $table->text('motif_retour')->nullable()->after('reponse');
        });
    }

    public function down(): void
    {
        Schema::table('transmissions', function (Blueprint $table) {
            $table->dropColumn('motif_retour');
        });
    }
};
