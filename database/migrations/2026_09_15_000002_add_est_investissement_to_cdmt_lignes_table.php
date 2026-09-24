<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cdmt_lignes', function (Blueprint $table) {
            $table->boolean('est_investissement')->default(false)->after('nature');
        });
    }

    public function down(): void
    {
        Schema::table('cdmt_lignes', function (Blueprint $table) {
            $table->dropColumn('est_investissement');
        });
    }
};
