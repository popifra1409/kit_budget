<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnels', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('matricule')->unique();
            $table->string('nom');
            $table->string('prenoms');
            // nom_complet sera géré par un accesseur Eloquent

            // Informations personnelles
            $table->enum('civilite', ['M.', 'Mme', 'Mlle'])->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->string('nationalite')->default('Camerounaise');

            // Contact
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->text('adresse')->nullable();

            // Informations professionnelles
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('fonction')->nullable();
            $table->string('grade')->nullable();
            $table->string('categorie')->nullable(); // A, B, C, D
            $table->string('echelon')->nullable();
            $table->string('indice')->nullable();

            // Dates importantes
            $table->date('date_prise_service')->nullable();
            $table->date('date_titularisation')->nullable();
            $table->date('date_depart_retraite')->nullable();

            // Informations bancaires
            $table->string('numero_cni')->nullable();
            $table->string('numero_cnps')->nullable();
            $table->string('numero_compte_bancaire')->nullable();
            $table->string('banque')->nullable();

            // Statut
            $table->enum('statut', [
                'actif',
                'conge',
                'detache',
                'disponibilite',
                'suspendu',
                'retraite',
                'demissionnaire',
            ])->default('actif');

            $table->boolean('actif')->default(true);

            // Relation avec User (optionnel)
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Si le personnel a un compte utilisateur');

            // Métadonnées
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['nom', 'prenoms']);
            $table->index('matricule');
            $table->index('service_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnels');
    }
};
