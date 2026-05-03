<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            $table->decimal('seuil_bon_commande_regie', 15, 2)
                ->default(5000000)
                ->after('seuil_achat_direct_regie')
                ->comment('Seuil TTC max pour BCR (au-dessus = marché public)');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_structure', function (Blueprint $table) {
            $table->dropColumn('seuil_bon_commande_regie');
        });
    }
};
