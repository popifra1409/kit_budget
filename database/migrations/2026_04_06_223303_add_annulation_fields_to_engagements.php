<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->timestamp('date_annulation')->nullable()->after('date_validation');
            $table->foreignId('annule_par')->nullable()->after('date_annulation')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropForeign(['annule_par']);
            $table->dropColumn(['date_annulation', 'annule_par']);
        });
    }
};
