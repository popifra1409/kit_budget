<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->text('motif')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_collectifs', function (Blueprint $table) {
            $table->string('motif', 255)->nullable()->change();
        });
    }
};
