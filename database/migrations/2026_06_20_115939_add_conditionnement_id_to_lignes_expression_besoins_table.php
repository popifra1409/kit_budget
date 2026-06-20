<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_expression_besoins', function (Blueprint $table) {
            $table->foreignId('conditionnement_id')->nullable()->after('article_id')
                ->constrained('conditionnements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lignes_expression_besoins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conditionnement_id');
        });
    }
};
