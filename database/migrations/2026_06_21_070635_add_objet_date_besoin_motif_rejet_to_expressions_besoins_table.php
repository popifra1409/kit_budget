<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expressions_besoins', function (Blueprint $table) {
            $table->text('objet')->nullable()->after('date_expression');
            $table->date('date_besoin')->nullable()->after('date_expression');
            $table->text('motif_rejet')->nullable()->after('date_validation');
        });
    }

    public function down(): void
    {
        Schema::table('expressions_besoins', function (Blueprint $table) {
            $table->dropColumn(['objet', 'date_besoin', 'motif_rejet']);
        });
    }
};
