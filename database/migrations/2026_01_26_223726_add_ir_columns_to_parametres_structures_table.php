<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {  // ← Singulier
            // Tranche 1
            $table->decimal('ir_tranche1_max', 15, 2)->default(500000)->after('taux_tva_defaut');
            $table->decimal('ir_tranche1_taux', 5, 2)->default(5.5)->after('ir_tranche1_max');

            // Tranche 2
            $table->decimal('ir_tranche2_min', 15, 2)->default(500001)->after('ir_tranche1_taux');
            $table->decimal('ir_tranche2_max', 15, 2)->default(3000000)->after('ir_tranche2_min');
            $table->decimal('ir_tranche2_taux', 5, 2)->default(11)->after('ir_tranche2_max');

            // Tranche 3
            $table->decimal('ir_tranche3_min', 15, 2)->default(3000001)->after('ir_tranche2_taux');
            $table->decimal('ir_tranche3_taux', 5, 2)->default(15)->after('ir_tranche3_min');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {  // ← Singulier
            $table->dropColumn([
                'ir_tranche1_max',
                'ir_tranche1_taux',
                'ir_tranche2_min',
                'ir_tranche2_max',
                'ir_tranche2_taux',
                'ir_tranche3_min',
                'ir_tranche3_taux',
            ]);
        });
    }
};
