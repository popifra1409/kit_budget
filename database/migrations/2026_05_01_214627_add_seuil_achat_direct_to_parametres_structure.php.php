<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            $table->decimal('seuil_achat_direct_regie', 15, 2)
                ->default(500000)
                ->after('fax')
                ->comment('Seuil TTC en dessous duquel une dépense régie est un achat direct');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            $table->dropColumn('seuil_achat_direct_regie');
        });
    }
};
