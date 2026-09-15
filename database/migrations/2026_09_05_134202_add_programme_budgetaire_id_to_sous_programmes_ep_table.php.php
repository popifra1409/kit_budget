<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            // Lien vers le Programme budgetaire correspondant (niveau='programme', codes 413/414...).
            // Ne JAMAIS pointer vers un Programme de niveau='sous_programme' (concept different, gestion interne).
            $table->foreignId('programme_budgetaire_id')->nullable()->after('plan_strategique_ep_id')
                ->constrained('programmes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programme_budgetaire_id');
        });
    }
};
