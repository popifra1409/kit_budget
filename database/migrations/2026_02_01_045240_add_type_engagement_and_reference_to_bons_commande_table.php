<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            // Vérifier et ajouter seulement si elles n'existent pas
            if (!Schema::hasColumn('bons_commande', 'type_engagement_id')) {
                $table->foreignId('type_engagement_id')
                    ->nullable()
                    ->after('numero')
                    ->constrained('types_engagement')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('bons_commande', 'reference')) {
                $table->string('reference', 100)
                    ->nullable()
                    ->after('numero')
                    ->comment('Référence externe du BC');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            if (Schema::hasColumn('bons_commande', 'type_engagement_id')) {
                $table->dropForeign(['type_engagement_id']);
                $table->dropColumn('type_engagement_id');
            }

            if (Schema::hasColumn('bons_commande', 'reference')) {
                $table->dropColumn('reference');
            }
        });
    }
};
