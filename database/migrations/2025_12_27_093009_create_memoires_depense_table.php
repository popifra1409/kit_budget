<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memoires_depense', function (Blueprint $table) {
            $table->id();

            // Numérotation
            $table->string('numero', 50)->unique()->comment('N° du mémoire');
            $table->integer('exercice')->comment('Année budgétaire');
            $table->date('date_memoire')->comment('Date du mémoire');

            // Références décision et CE
            $table->string('numero_decision', 100)->nullable()->comment('N° de la décision');
            $table->date('date_decision')->nullable()->comment('Date de la décision');
            $table->string('numero_ce', 100)->nullable()->comment('N° certificat engagement');
            $table->date('date_ce')->nullable()->comment('Date du CE');

            // Liens avec documents sources
            $table->foreignId('bordereau_engagement_id')->nullable()
                ->constrained('bordereaux_engagement')
                ->nullOnDelete();

            $table->foreignId('bon_commande_id')->nullable()
                ->constrained('bons_commande')
                ->nullOnDelete();

            // Objet et description
            $table->text('objet')->comment('Objet de la dépense');
            $table->text('observations')->nullable();

            // Montants calculés
            $table->decimal('montant_ht', 15, 2)->default(0);
            $table->decimal('montant_tva', 15, 2)->default(0);
            $table->decimal('montant_ir', 15, 2)->default(0);
            $table->decimal('montant_ttc', 15, 2)->default(0);
            $table->decimal('montant_net', 15, 2)->default(0)->comment('Net à payer');

            // Montant en lettres (pré-calculé pour le PDF)
            $table->text('montant_lettres')->nullable()->comment('Montant TTC en lettres');

            // Signatures
            $table->string('signataire_nom')->nullable();
            $table->string('signataire_fonction')->nullable();
            $table->date('date_signature')->nullable();
            $table->string('lieu_signature', 100)->default('Yaoundé');

            // Statut
            $table->enum('statut', ['brouillon', 'valide', 'transmis', 'approuve', 'annule'])
                ->default('brouillon');

            // Fichier PDF généré
            $table->string('fichier_pdf')->nullable()->comment('Chemin du PDF généré');

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('numero');
            $table->index('exercice');
            $table->index('statut');
            $table->index('date_memoire');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memoires_depense');
    }
};
