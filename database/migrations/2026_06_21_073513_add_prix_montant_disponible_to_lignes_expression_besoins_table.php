<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_expression_besoins', function (Blueprint $table) {
            $table->decimal('prix_unitaire_estime', 15, 2)->default(0)->after('quantite_a_commander');
            $table->decimal('montant_estime', 15, 2)->default(0)->after('prix_unitaire_estime');
            $table->boolean('disponible_en_stock')->default(false)->after('quantite_en_stock');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_expression_besoins', function (Blueprint $table) {
            $table->dropColumn(['prix_unitaire_estime', 'montant_estime', 'disponible_en_stock']);
        });
    }
};
