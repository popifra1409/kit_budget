<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            if (!Schema::hasColumn('decisions_administratives', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Utilisateur qui a créé la décision');
            }

            if (!Schema::hasColumn('decisions_administratives', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Dernier utilisateur qui a modifié la décision');
            }
        });

        // Mettre à jour les enregistrements existants avec l'utilisateur ID 1
        $firstUser = DB::table('users')->orderBy('id', 'asc')->first();

        if ($firstUser) {
            DB::table('decisions_administratives')
                ->whereNull('created_by')
                ->update([
                    'created_by' => $firstUser->id,
                    'updated_by' => $firstUser->id,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            if (Schema::hasColumn('decisions_administratives', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('decisions_administratives', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
        });
    }
};
