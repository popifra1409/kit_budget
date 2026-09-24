<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->enum('type', ['operationnel', 'support'])
                ->default('operationnel')
                ->after('libelle');
        });
    }

    public function down(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
