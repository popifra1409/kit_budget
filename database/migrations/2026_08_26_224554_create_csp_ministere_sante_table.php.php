<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csp_ministere_sante', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();           // requis par HasWorkflow (notifications)
            $table->string('code')->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->string('statut')->default('brouillon'); // brouillon, en_transmission, valide, en_vigueur, cloture
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csp_ministere_sante');
    }
};
