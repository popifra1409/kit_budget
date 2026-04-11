<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etat_configs', function (Blueprint $table) {
            $table->string('format_papier')->default('A4')->after('options_pdf');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait')->after('format_papier');
        });
    }

    public function down(): void
    {
        Schema::table('etat_configs', function (Blueprint $table) {
            $table->dropColumn(['format_papier', 'orientation']);
        });
    }
};
