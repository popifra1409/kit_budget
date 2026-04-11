<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Contenu
            $table->text('contenu')->nullable();
            $table->enum('type', ['texte', 'fichier', 'systeme'])->default('texte');

            // Lecture
            $table->json('lu_par')->nullable(); // Array des user_ids ayant lu

            // Threading (répondre à un message)
            $table->foreignId('reponse_a_message_id')->nullable()->constrained('messages')->nullOnDelete();

            // Édition/Suppression
            $table->timestamp('edite_at')->nullable();
            $table->boolean('est_supprime')->default(false);

            // Métadonnées
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index(['conversation_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
