<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — paramétrage 5/5.
 *
 * « Composition de la commission de département : nom du membre, titre du membre
 * (président, secrétaire…), N° téléphone » (scan du 27/10 17.53).
 *
 * Une commission est un référentiel rattaché à un service (le « département »), et non
 * une liste recréée pour chaque marché : sinon la fiche de présence devient une
 * ressaisie à chaque procédure. Le marché épinglera une commission (étape D).
 *
 * Le membre est un utilisateur OU un nom libre, comme le fait déjà receptions
 * (representant_prestataire + representant_prestataire_nom) : les commissions
 * intègrent des personnes extérieures à l'organigramme.
 *
 * La fiche de présence et son visa ne sont pas créés ici — étape E.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->foreignId('service_id')->nullable()
                ->constrained('services')->nullOnDelete()
                ->comment('Département / service dont relève la commission');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('commission_membres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Membre interne : le nom et le téléphone restent saisis, pour les membres extérieurs');
            $table->string('nom');
            $table->enum('titre', array_keys(config('marches.titres_membre')))
                ->comment('Qualité dans la commission : président, rapporteur, secrétaire…');
            $table->string('telephone', 30)->nullable();
            $table->integer('ordre')->default(0);
            $table->timestamps();

            $table->index(['commission_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_membres');
        Schema::dropIfExists('commissions');
    }
};
