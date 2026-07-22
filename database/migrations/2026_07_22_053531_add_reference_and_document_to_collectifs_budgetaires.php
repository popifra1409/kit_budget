<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('collectifs_budgetaires', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('numero');
            $table->string('document_path')->nullable()->after('reference');
        });
    }

    public function down()
    {
        Schema::table('collectifs_budgetaires', function (Blueprint $table) {
            $table->dropColumn(['reference', 'document_path']);
        });
    }
};
