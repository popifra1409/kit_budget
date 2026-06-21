<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expressions_besoins', function (Blueprint $table) {
            $table->foreignId('signe_par_id')->nullable()->after('ordonnateur_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('date_signature_dg')->nullable()->after('signe_par_id');
            $table->foreignId('fiche_consolidation_id')->nullable()->after('id')
                ->constrained('fiches_consolidation_besoins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expressions_besoins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signe_par_id');
            $table->dropColumn('date_signature_dg');
            $table->dropConstrainedForeignId('fiche_consolidation_id');
        });
    }
};
