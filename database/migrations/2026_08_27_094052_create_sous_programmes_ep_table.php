<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sous_programmes_ep', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique(); // requis par HasWorkflow
            $table->foreignId('plan_strategique_ep_id')->constrained('plans_strategiques_ep')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut')->default('brouillon'); // brouillon, en_transmission, valide, en_vigueur, cloture
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sous_programmes_ep');
    }
};
