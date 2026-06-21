<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->foreignId('expression_besoin_id')->nullable()->after('service_demandeur_id')
                ->constrained('expressions_besoins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expression_besoin_id');
        });
    }
};
