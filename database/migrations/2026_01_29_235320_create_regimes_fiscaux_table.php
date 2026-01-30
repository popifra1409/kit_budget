<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regimes_fiscaux', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('code')->unique(); // IGS, REEL
            $table->string('libelle');
            $table->text('description')->nullable();

            // Seuils de CA (en FCFA)
            $table->decimal('ca_min', 15, 2)->nullable();
            $table->decimal('ca_max', 15, 2)->nullable();

            // Taux IR par défaut (%)
            $table->decimal('taux_ir_defaut', 5, 2)->default(0);

            // Type de calcul IR
            $table->enum('type_calcul_ir', [
                'pourcentage',      // X% du montant HT
                'forfaitaire',      // Montant fixe
                'tranche'           // Par tranche de CA
            ])->default('pourcentage');

            // Statut
            $table->boolean('actif')->default(true);

            // Ordre d'affichage
            $table->integer('ordre')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regimes_fiscaux');
    }
};
