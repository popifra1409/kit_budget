<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('titre')->nullable();
            $table->enum('type', ['individuel', 'groupe', 'document'])->default('individuel');

            // Lien avec un document (optionnel - polymorphique)
            $table->string('document_type')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();

            // Créateur
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Métadonnées
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['document_type', 'document_id']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
