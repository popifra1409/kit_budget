<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppa_exercices', function (Blueprint $table) {
            $table->foreignId('cdmt_exercice_id')->nullable()->after('plan_strategique_ep_id')
                ->constrained('cdmt_exercices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ppa_exercices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cdmt_exercice_id');
        });
    }
};
