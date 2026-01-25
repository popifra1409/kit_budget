<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne n'existe pas déjà
        if (!Schema::hasColumn('users', 'actif')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('actif')->default(true)->after('password');
            });

            echo "✅ Colonne 'actif' ajoutée à la table users\n";
        } else {
            echo "ℹ️  Colonne 'actif' existe déjà\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'actif')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('actif');
            });
        }
    }
};
