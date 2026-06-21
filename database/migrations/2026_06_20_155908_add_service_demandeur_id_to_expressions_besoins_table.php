<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expressions_besoins', function (Blueprint $table) {
            $table->foreignId('service_demandeur_id')->nullable()->after('service_demandeur')
                ->constrained('services')->nullOnDelete();
        });

        // ✅ Tentative de rapprochement automatique avec les services existants
        // (correspondance insensible à la casse/espaces). Les expressions sans
        // correspondance resteront service_demandeur_id = null (à corriger manuellement).
        $expressions = DB::table('expressions_besoins')
            ->whereNotNull('service_demandeur')
            ->where('service_demandeur', '!=', '')
            ->get(['id', 'service_demandeur']);

        foreach ($expressions as $eb) {
            $service = DB::table('services')
                ->whereRaw('LOWER(TRIM(nom)) = ?', [strtolower(trim($eb->service_demandeur))])
                ->first();

            if ($service) {
                DB::table('expressions_besoins')
                    ->where('id', $eb->id)
                    ->update(['service_demandeur_id' => $service->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('expressions_besoins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_demandeur_id');
        });
    }
};
