<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Rôle dans la conversation
            $table->enum('role', ['admin', 'membre', 'lecteur'])->default('membre');

            // Suivi de lecture
            $table->timestamp('derniere_lecture_at')->nullable();

            // Notifications
            $table->boolean('notifications_activees')->default(true);

            $table->timestamps();

            // Index
            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
