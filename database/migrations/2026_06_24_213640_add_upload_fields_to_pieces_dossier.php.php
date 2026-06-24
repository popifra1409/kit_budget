<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces_dossier', function (Blueprint $table) {
            // ✅ Champ upload pour les pièces numérisées par l'utilisateur
            if (!Schema::hasColumn('pieces_dossier', 'fichier_upload')) {
                $table->string('fichier_upload')->nullable()->after('chemin_fichier');
            }
            if (!Schema::hasColumn('pieces_dossier', 'libelle')) {
                $table->string('libelle')->nullable()->after('type_piece');
            }
            if (!Schema::hasColumn('pieces_dossier', 'observations')) {
                $table->text('observations')->nullable()->after('libelle');
            }
            // ✅ Source automatique vs manuelle
            if (!Schema::hasColumn('pieces_dossier', 'source')) {
                $table->enum('source', ['automatique', 'manuelle'])->default('automatique')->after('type_piece');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pieces_dossier', function (Blueprint $table) {
            $table->dropColumnIfExists('fichier_upload');
            $table->dropColumnIfExists('libelle');
            $table->dropColumnIfExists('observations');
            $table->dropColumnIfExists('source');
        });
    }
};
