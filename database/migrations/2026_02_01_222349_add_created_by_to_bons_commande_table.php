<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            if (!Schema::hasColumn('bons_commande', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('bons_commande', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Mettre à jour les enregistrements existants
        $firstAdmin = DB::table('users')->where('id', 1)->first();
        if ($firstAdmin) {
            DB::table('bons_commande')
                ->whereNull('created_by')
                ->update(['created_by' => $firstAdmin->id]);
        }
    }

    public function down(): void
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            if (Schema::hasColumn('bons_commande', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('bons_commande', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
        });
    }
};
