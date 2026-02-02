<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_bon_commande', function (Blueprint $table) {
            if (!Schema::hasColumn('lignes_bon_commande', 'reference_mercuriale_id')) {
                $table->foreignId('reference_mercuriale_id')
                    ->nullable()
                    ->after('nomenclature_id')
                    ->constrained('reference_mercuriales')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('lignes_bon_commande', 'reference_personnalisee')) {
                $table->string('reference_personnalisee')
                    ->nullable()
                    ->after('reference_mercuriale_id')
                    ->comment('Référence pour saisie manuelle');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lignes_bon_commande', function (Blueprint $table) {
            if (Schema::hasColumn('lignes_bon_commande', 'reference_mercuriale_id')) {
                $table->dropForeign(['reference_mercuriale_id']);
                $table->dropColumn('reference_mercuriale_id');
            }

            if (Schema::hasColumn('lignes_bon_commande', 'reference_personnalisee')) {
                $table->dropColumn('reference_personnalisee');
            }
        });
    }
};
