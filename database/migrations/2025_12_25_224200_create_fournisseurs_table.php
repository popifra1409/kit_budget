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
        // TABLE FOURNISSEURS
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Code fournisseur (ex: FRS-001)');
            $table->string('raison_sociale')->comment('Raison sociale');
            $table->string('sigle')->nullable()->comment('Sigle/Nom commercial');

            // Identification fiscale et juridique
            $table->string('rccm')->nullable()->comment('Numéro RCCM');
            $table->string('nif')->nullable()->comment('Numéro d\'Identification Fiscale');
            $table->string('forme_juridique')->nullable()->comment('SARL, SA, EI, etc.');

            // Contact
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('pays')->default('Cameroun');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();

            // Contact principal
            $table->string('contact_nom')->nullable()->comment('Nom du contact principal');
            $table->string('contact_fonction')->nullable()->comment('Fonction du contact');
            $table->string('contact_telephone')->nullable();
            $table->string('contact_email')->nullable();

            // Informations bancaires
            $table->string('banque')->nullable()->comment('Nom de la banque');
            $table->string('iban')->nullable()->comment('IBAN');
            $table->string('code_swift')->nullable()->comment('Code SWIFT/BIC');
            $table->string('numero_compte')->nullable()->comment('Numéro de compte');

            // Catégorie fournisseur
            $table->enum('type', ['biens', 'services', 'travaux', 'mixte'])->default('mixte')
                ->comment('Type de fournisseur');

            // Statut
            $table->boolean('actif')->default(true);
            $table->boolean('blackliste')->default(false)->comment('Fournisseur blacklisté');
            $table->text('motif_blacklist')->nullable();

            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('raison_sociale');
            $table->index('actif');
        });

        DB::statement("COMMENT ON TABLE fournisseurs IS 'Fournisseurs et prestataires'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};
