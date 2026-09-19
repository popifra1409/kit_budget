<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans_strategiques_ep', function (Blueprint $table) {
            $table->text('contexte_elaboration')->nullable()->after('description');
            $table->text('domaines_intervention')->nullable()->after('contexte_elaboration');
            $table->text('objectif_strategique')->nullable()->after('domaines_intervention');
        });
    }

    public function down(): void
    {
        Schema::table('plans_strategiques_ep', function (Blueprint $table) {
            $table->dropColumn(['contexte_elaboration', 'domaines_intervention', 'objectif_strategique']);
        });
    }
};
