<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regies_avances', function (Blueprint $table) {
            if (!Schema::hasColumn('regies_avances', 'encaisse_annuelle'))
                $table->decimal('encaisse_annuelle', 15, 2)->default(0)->after('montant_alloue');

            if (!Schema::hasColumn('regies_avances', 'objet'))
                $table->text('objet')->nullable()->after('libelle');
        });
    }

    public function down(): void
    {
        Schema::table('regies_avances', function (Blueprint $table) {
            $table->dropColumn(['encaisse_annuelle', 'objet']);
        });
    }
};
