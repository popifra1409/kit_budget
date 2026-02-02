<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->foreignId('service_beneficiaire_id')
                ->nullable()
                ->after('service_demandeur_id')
                ->constrained('services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropForeign(['service_beneficiaire_id']);
            $table->dropColumn('service_beneficiaire_id');
        });
    }
};
