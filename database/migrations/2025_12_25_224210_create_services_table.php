<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // TABLE SERVICES (Services demandeurs/bénéficiaires)
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Code du service (ex: SRV-001)');
            $table->string('nom')->comment('Nom du service');
            $table->text('description')->nullable()->comment('Description du service');

            // Hiérarchie (optionnel)
            $table->foreignId('parent_id')->nullable()->constrained('services')->onDelete('set null')
                ->comment('Service parent (pour hiérarchie)');

            // Responsable
            $table->string('responsable')->nullable()->comment('Nom du responsable du service');
            $table->string('email')->nullable()->comment('Email du service');
            $table->string('telephone')->nullable()->comment('Téléphone du service');

            // Localisation
            $table->string('batiment')->nullable()->comment('Bâtiment');
            $table->string('bureau')->nullable()->comment('Numéro de bureau');

            $table->boolean('actif')->default(true)->comment('Service actif');
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('actif');
        });

        DB::statement("COMMENT ON TABLE services IS 'Services demandeurs/bénéficiaires des commandes'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
