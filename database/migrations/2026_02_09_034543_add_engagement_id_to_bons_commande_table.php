<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            if (!Schema::hasColumn('bons_commande', 'engagement_id')) {
                $table->foreignId('engagement_id')
                    ->nullable()
                    ->after('budget_id')
                    ->constrained('engagements')
                    ->nullOnDelete();

                $table->index('engagement_id');
            }
        });
    }

    public function down()
    {
        Schema::table('bons_commande', function (Blueprint $table) {
            if (Schema::hasColumn('bons_commande', 'engagement_id')) {
                $table->dropForeign(['engagement_id']);
                $table->dropIndex(['engagement_id']);
                $table->dropColumn('engagement_id');
            }
        });
    }
};
