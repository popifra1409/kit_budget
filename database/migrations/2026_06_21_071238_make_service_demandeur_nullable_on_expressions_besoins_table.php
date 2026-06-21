<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE expressions_besoins ALTER COLUMN service_demandeur DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE expressions_besoins ALTER COLUMN service_demandeur SET NOT NULL');
    }
};
