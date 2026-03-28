<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fiches_stock', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();                       // N° fiche
            $table->foreignId('article_id')->constrained();
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->enum('type_mouvement', ['entree', 'sortie', 'ajustement', 'retour']);
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 15, 2)->default(0);
            $table->decimal('valeur_totale', 15, 2)->default(0);
            $table->integer('stock_avant')->default(0);
            $table->integer('stock_apres')->default(0);
            $table->date('date_mouvement');
            $table->text('motif')->nullable();
            // Référence au document source (polymorphique)
            $table->string('document_type')->nullable();              // ReceptionBien, OrdreSortie...
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiches_stock');
    }
};
