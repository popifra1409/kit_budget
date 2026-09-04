<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions_sous_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique(); // requis par HasWorkflow
            $table->foreignId('sous_programme_ep_id')->constrained('sous_programmes_ep')->restrictOnDelete();
            $table->string('code')->unique(); // ex: 01, 02 (codification budgetaire, cf. guide d'arrimage)
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
        Schema::dropIfExists('actions_sous_programmes');
    }
};
