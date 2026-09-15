<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actions_sous_programmes', function (Blueprint $table) {
            // Lien vers l'Action budgetaire correspondante (module Budget/Cadre Logique)
            $table->foreignId('action_budgetaire_id')->nullable()->after('sous_programme_ep_id')
                ->constrained('actions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('actions_sous_programmes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('action_budgetaire_id');
        });
    }
};
