<?php
// database/migrations/xxxx_create_modes_paiement_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modes_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->string('icone')->nullable()->default('💳');
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->boolean('applicable_op')->default(true);
            $table->boolean('applicable_opt')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // Données par défaut
        DB::table('modes_paiement')->insert([
            ['code' => 'virement',       'libelle' => 'Virement bancaire',       'icone' => '🏦', 'ordre' => 1, 'actif' => true, 'applicable_op' => true,  'applicable_opt' => true,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'cheque',         'libelle' => 'Chèque',                  'icone' => '📄', 'ordre' => 2, 'actif' => true, 'applicable_op' => true,  'applicable_opt' => true,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ordre_virement', 'libelle' => 'Ordre de virement',       'icone' => '📋', 'ordre' => 3, 'actif' => true, 'applicable_op' => true,  'applicable_opt' => true,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'especes',        'libelle' => 'Espèces',                 'icone' => '💵', 'ordre' => 4, 'actif' => true, 'applicable_op' => true,  'applicable_opt' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'mandat',         'libelle' => 'Mandat postal',           'icone' => '📮', 'ordre' => 5, 'actif' => true, 'applicable_op' => true,  'applicable_opt' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'mobile_money',   'libelle' => 'Mobile Money (MTN/Orange)', 'icone' => '📱', 'ordre' => 6, 'actif' => true, 'applicable_op' => true, 'applicable_opt' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'autre',          'libelle' => 'Autre',                   'icone' => '🔷', 'ordre' => 7, 'actif' => true, 'applicable_op' => true,  'applicable_opt' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);

        // Ajouter mode_paiement dans ordonnances_paiement si absent
        if (!Schema::hasColumn('ordonnances_paiement', 'mode_paiement')) {
            Schema::table('ordonnances_paiement', function (Blueprint $table) {
                $table->string('mode_paiement')->nullable()->after('reference_paiement');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modes_paiement');
        Schema::table('ordonnances_paiement', function (Blueprint $table) {
            $table->dropColumn('mode_paiement');
        });
    }
};
