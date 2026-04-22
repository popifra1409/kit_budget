<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE avenants DROP CONSTRAINT IF EXISTS avenants_type_correction_check");
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE avenants ALTER COLUMN type_correction TYPE VARCHAR(50)"
        );
    }
    public function down(): void {}
};
