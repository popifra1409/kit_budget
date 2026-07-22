<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Table des groupes de nomenclature ─────────────────
        Schema::create('groupes_nomenclature', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->enum('type', ['depense', 'recette'])->index();
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // ── Ajouter groupe_id dans nomenclature_budgetaire ────
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            $table->unsignedBigInteger('groupe_id')
                ->nullable()
                ->after('classe')
                ->comment('Groupe de nomenclature (ex: Charges de personnel, Recettes fiscales)');
            $table->foreign('groupe_id')
                ->references('id')
                ->on('groupes_nomenclature')
                ->nullOnDelete();
        });

        // ── Données de base — Dépenses ─────────────────────────
        DB::table('groupes_nomenclature')->insert([
            // Dépenses
            ['code' => 'CHARGES_PERSONNEL',    'libelle' => 'Charges de personnel',            'type' => 'depense', 'ordre' => 1,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ACHATS_FOURNITURES',   'libelle' => 'Achats et fournitures',           'type' => 'depense', 'ordre' => 2,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SERVICES_EXTERIEURS',  'libelle' => 'Services extérieurs',             'type' => 'depense', 'ordre' => 3,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AUTRES_CHARGES',       'libelle' => 'Autres charges',                  'type' => 'depense', 'ordre' => 4,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CHARGES_FINANCIERES',  'libelle' => 'Charges financières',             'type' => 'depense', 'ordre' => 5,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'INVESTISSEMENTS',      'libelle' => 'Investissements & équipements',   'type' => 'depense', 'ordre' => 6,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'TRANSFERTS_DEPENSE',   'libelle' => 'Transferts et subventions',       'type' => 'depense', 'ordre' => 7,  'created_at' => now(), 'updated_at' => now()],
            // Recettes
            ['code' => 'RECETTES_PROPRES',     'libelle' => 'Recettes propres',                'type' => 'recette', 'ordre' => 1,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SUBVENTIONS_ETAT',     'libelle' => 'Subventions de l\'État',          'type' => 'recette', 'ordre' => 2,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'DONS_LEGS',            'libelle' => 'Dons et legs',                    'type' => 'recette', 'ordre' => 3,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EMPRUNTS',             'libelle' => 'Emprunts et financements',        'type' => 'recette', 'ordre' => 4,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'RESERVES',             'libelle' => 'Réserves et reports',             'type' => 'recette', 'ordre' => 5,  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AUTRES_RECETTES',      'libelle' => 'Autres recettes',                 'type' => 'recette', 'ordre' => 6,  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('nomenclature_budgetaire', function (Blueprint $table) {
            $table->dropForeign(['groupe_id']);
            $table->dropColumn('groupe_id');
        });
        Schema::dropIfExists('groupes_nomenclature');
    }
};
