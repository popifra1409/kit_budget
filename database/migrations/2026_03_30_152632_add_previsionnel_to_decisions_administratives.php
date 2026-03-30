<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            // ✅ Flag prévisionnelle
            $table->boolean('est_previsionnel')->default(false)->after('mode_saisie');

            // ✅ Lien vers DA réelle si convertie
            $table->foreignId('da_reelle_id')
                ->nullable()
                ->after('est_previsionnel')
                ->constrained('decisions_administratives')
                ->nullOnDelete();

            $table->index('est_previsionnel');
        });

        // ✅ Ajouter 'previsionnel' au type d'engagement si nécessaire
        // (engagements liés aux DA prévisionnelles)
        if (Schema::hasColumn('engagements', 'type')) {
            \DB::statement("
                ALTER TABLE engagements
                DROP CONSTRAINT IF EXISTS engagements_type_check
            ");
            \DB::statement("
                ALTER TABLE engagements
                ADD CONSTRAINT engagements_type_check
                CHECK (type IN ('standard', 'impot', 'previsionnel'))
            ");
        }
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropForeign(['da_reelle_id']);
            $table->dropColumn(['est_previsionnel', 'da_reelle_id']);
        });
    }
};