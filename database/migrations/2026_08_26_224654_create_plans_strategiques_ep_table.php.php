<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_strategiques_ep', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('csp_ministere_id')->constrained('csp_ministere_sante')->restrictOnDelete();
            $table->foreignId('parametres_structure_id')->nullable()
                ->constrained('parametres_structure')->nullOnDelete(); // rattachement a l'EP (singleton)
            $table->string('code')->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->string('statut')->default('brouillon'); // brouillon, en_transmission, valide, en_vigueur, cloture
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans_strategiques_ep');
    }
};
