<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses_regies', function (Blueprint $table) {
            if (!Schema::hasColumn('depenses_regies', 'provision_ligne_regie_id')) {
                $table->foreignId('provision_ligne_regie_id')
                    ->nullable()
                    ->after('ligne_regie_avance_id')
                    ->constrained('provisions_lignes_regies')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('depenses_regies', 'mode_saisie')) {
                $table->string('mode_saisie')
                    ->default('montant_nap')
                    ->after('type_depense');
            }
        });
    }
};
