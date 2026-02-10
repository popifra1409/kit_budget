<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->foreignId('engagement_id')
                ->nullable()
                ->after('budget_id')
                ->constrained('engagements')
                ->nullOnDelete();

            $table->index('engagement_id');
        });
    }

    public function down()
    {
        Schema::table('decisions_administratives', function (Blueprint $table) {
            $table->dropForeign(['engagement_id']);
            $table->dropColumn('engagement_id');
        });
    }
};
