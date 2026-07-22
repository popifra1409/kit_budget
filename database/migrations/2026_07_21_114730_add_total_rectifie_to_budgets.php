<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // database/migrations/xxxx_xx_xx_xxxxxx_add_total_rectifie_to_budgets.php
    public function up()
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->decimal('total_rectifie', 15, 2)->default(0)->nullable();
        });
    }

    public function down()
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn('total_rectifie');
        });
    }
};
