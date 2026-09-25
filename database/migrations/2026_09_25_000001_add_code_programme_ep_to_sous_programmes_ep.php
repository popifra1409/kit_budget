<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table((new \App\Models\SousProgrammeEp)->getTable(), function (Blueprint $table) {
            // Code du programme budgetaire de l'EP qui porte les actions (ex: SP-1).
            // Lien par CODE et non par id : les programmes sont recrees a chaque exercice.
            $table->string('code_programme_ep', 50)->nullable()->after('programme_budgetaire_id');
            $table->index('code_programme_ep');
        });
    }

    public function down(): void
    {
        Schema::table((new \App\Models\SousProgrammeEp)->getTable(), function (Blueprint $table) {
            $table->dropIndex(['code_programme_ep']);
            $table->dropColumn('code_programme_ep');
        });
    }
};
