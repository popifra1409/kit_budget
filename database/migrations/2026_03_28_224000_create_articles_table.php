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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                        
            $table->string('designation');                           
            $table->text('description')->nullable();
            $table->enum('type', ['durable', 'consomptible']);       
            $table->string('unite_mesure')->default('unité');         
            $table->string('categorie')->nullable();                  // Catégorie : mobilier, IT...
            $table->string('marque')->nullable();
            $table->string('reference_fournisseur')->nullable();
            $table->decimal('prix_unitaire_moyen', 15, 2)->default(0); // Prix unitaire moyen pondéré
            $table->integer('seuil_alerte')->default(0);              // Stock minimum
            $table->boolean('actif')->default(true);
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
