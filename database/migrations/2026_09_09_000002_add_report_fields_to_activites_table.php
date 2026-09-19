<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activites', function (Blueprint $table) {
            $table->text('objectif')->nullable()->after('description');
            $table->string('zone_execution')->nullable()->after('objectif');
            $table->foreignId('responsable_id')->nullable()->after('zone_execution')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
            $table->dropColumn(['objectif', 'zone_execution']);
        });
    }
};
