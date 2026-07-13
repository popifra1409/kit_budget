<?php
// database/migrations/xxxx_add_mode_paiement_to_ordonnances_paiement.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->string('mode_paiement')->nullable()->after('reference_paiement')
                ->comment('virement|cheque|especes|ordre_virement|mandat|mobile_money|autre');
        });
    }
    public function down(): void
    {
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->dropColumn('mode_paiement');
        });
    }
};
