<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_log', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('causer_id');
            }
            if (!Schema::hasColumn('activity_log', 'user_agent')) {
                $table->string('user_agent')->nullable()->after('ip_address');
            }
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
