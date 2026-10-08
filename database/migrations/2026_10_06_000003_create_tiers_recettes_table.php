<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des DÉBITEURS / PAYEURS des recettes (caisse principale, État, assureurs,
 * sociétés conventionnées, donateurs…), avec une catégorie pour le suivi du RAR par tiers.
 * Reprise : chaque nom distinct déjà saisi dans recettes_reelles.payeur devient un tiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers_recettes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('categorie', 30)->default('autre');
            $table->string('telephone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->text('observations')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('nom');   // unicité contrôlée par le formulaire (suppression récupérable)
            $table->index('categorie');
        });

        Schema::table('recettes_reelles', function (Blueprint $table) {
            $table->foreignId('tiers_recette_id')->nullable()->after('payeur')
                ->constrained('tiers_recettes')->nullOnDelete();
        });

        // Reprise des payeurs déjà saisis
        $noms = DB::table('recettes_reelles')->whereNotNull('payeur')->where('payeur', '!=', '')
            ->distinct()->pluck('payeur');

        foreach ($noms as $nom) {
            $nom = trim($nom);
            if ($nom === '') continue;

            $id = DB::table('tiers_recettes')->where('nom', $nom)->value('id')
                ?? DB::table('tiers_recettes')->insertGetId([
                    'nom' => $nom,
                    'categorie' => 'autre',
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('recettes_reelles')->where('payeur', $nom)->update(['tiers_recette_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('recettes_reelles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tiers_recette_id');
        });
        Schema::dropIfExists('tiers_recettes');
    }
};
