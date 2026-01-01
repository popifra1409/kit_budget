<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etat_configs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->string('template');
            $table->text('description')->nullable();
            $table->json('champs_variables')->nullable();
            $table->json('calculs')->nullable();
            $table->json('entete_config')->nullable();
            $table->json('pied_page_config')->nullable();
            $table->json('signature_config')->nullable();
            $table->json('options_pdf')->nullable();
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->string('categorie')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etat_configs');
    }
};
