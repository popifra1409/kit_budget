<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('valeur_stock', 15, 2)->default(0)->after('quantite_commandee');
            $table->date('date_inventaire')->nullable()->after('date_dernier_mouvement');
            $table->integer('quantite_inventaire')->nullable()->after('date_inventaire');
            $table->text('observations')->nullable()->after('quantite_inventaire');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['valeur_stock', 'date_inventaire', 'quantite_inventaire', 'observations']);
        });
    }
};
