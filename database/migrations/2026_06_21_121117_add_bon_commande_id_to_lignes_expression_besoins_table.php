<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_expression_besoins', function (Blueprint $table) {
            $table->foreignId('bon_commande_id')->nullable()->after('conditionnement_id')
                ->constrained('bons_commande')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lignes_expression_besoins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bon_commande_id');
        });
    }
};
