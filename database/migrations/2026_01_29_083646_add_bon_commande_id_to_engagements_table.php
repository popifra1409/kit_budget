<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->foreignId('bon_commande_id')
                ->nullable()
                ->after('id')
                ->constrained('bons_commande')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropForeign(['bon_commande_id']);
            $table->dropColumn('bon_commande_id');
        });
    }
};
