<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activites', function (Blueprint $table) {
            $table->string('numero')->nullable()->unique()->after('id');
            $table->string('statut')->nullable()->default('brouillon')->after('actif');
            $table->foreignId('created_by')->nullable()->after('statut')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['numero', 'statut']);
        });
    }
};
