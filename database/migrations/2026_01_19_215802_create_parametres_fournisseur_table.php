<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parametres_fournisseur', function (Blueprint $table) {
            $table->id();
            
            // ====================================
            // INFORMATIONS SOCIÉTÉ ÉDITRICE
            // ====================================
            $table->string('nom_societe', 255)->comment('Nom complet de la société éditrice');
            $table->string('sigle', 50)->nullable()->comment('Sigle de la société');
            $table->string('logo', 255)->nullable()->comment('Chemin du logo de la société');
            
            // ====================================
            // COORDONNÉES
            // ====================================
            $table->text('adresse')->nullable()->comment('Adresse complète');
            $table->string('ville', 100)->nullable();
            $table->string('pays', 100)->nullable();
            $table->string('code_postal', 20)->nullable();
            $table->string('boite_postale', 50)->nullable();
            
            $table->string('telephone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email_general', 255)->nullable()->comment('Email général de contact');
            $table->string('email_support', 255)->nullable()->comment('Email du support technique');
            $table->string('email_commercial', 255)->nullable()->comment('Email commercial');
            $table->string('site_web', 255)->nullable();
            
            // ====================================
            // INFORMATIONS LÉGALES
            // ====================================
            $table->string('numero_contribuable', 100)->nullable();
            $table->string('rccm', 100)->nullable()->comment('Registre du Commerce');
            $table->string('forme_juridique', 100)->nullable()->comment('SARL, SA, SAS, etc.');
            $table->string('numero_agrement', 100)->nullable()->comment('Numéro d\'agrément si applicable');
            
            // ====================================
            // INFORMATIONS LOGICIEL
            // ====================================
            $table->string('nom_logiciel', 255)->default('Budget Manager')->comment('Nom commercial du logiciel');
            $table->string('version_logiciel', 20)->default('1.0.0')->comment('Version actuelle');
            $table->text('description_logiciel')->nullable()->comment('Description pour le footer');
            $table->string('url_documentation', 255)->nullable()->comment('URL de la documentation');
            $table->string('url_guide_utilisateur', 255)->nullable();
            
            // ====================================
            // SUPPORT & CONTACT
            // ====================================
            $table->string('telephone_support', 50)->nullable()->comment('Numéro hotline support');
            $table->string('telephone_urgence', 50)->nullable()->comment('Numéro d\'urgence 24/7');
            $table->text('horaires_support')->nullable()->comment('Horaires d\'ouverture du support');
            
            // ====================================
            // INFORMATIONS COPYRIGHT
            // ====================================
            $table->string('copyright_texte', 255)->nullable()->comment('Texte du copyright');
            $table->integer('copyright_annee_debut')->nullable()->comment('Année de création');
            $table->text('mentions_legales')->nullable();
            $table->text('conditions_utilisation')->nullable();
            
            // ====================================
            // RÉSEAUX SOCIAUX
            // ====================================
            $table->string('facebook', 255)->nullable();
            $table->string('twitter', 255)->nullable();
            $table->string('linkedin', 255)->nullable();
            $table->string('youtube', 255)->nullable();
            
            // ====================================
            // PARAMÈTRES SYSTÈME
            // ====================================
            $table->boolean('actif')->default(true)->comment('Paramétrage actif (singleton)');
            $table->boolean('afficher_footer')->default(true)->comment('Afficher le footer');
            $table->boolean('afficher_badge_licence')->default(true)->comment('Afficher le badge de licence');
            $table->string('couleur_principale', 7)->default('#0ea5e9')->comment('Couleur primaire (hex)');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index
            $table->index('actif');
        });

        // Insérer un enregistrement par défaut
        DB::table('parametres_fournisseur')->insert([
            'nom_societe' => 'Votre Société',
            'sigle' => 'VS',
            'email_general' => 'contact@votresociete.com',
            'email_support' => 'support@votresociete.com',
            'telephone_support' => '+237 00 00 00 00',
            'nom_logiciel' => 'Budget Manager',
            'version_logiciel' => '1.0.0',
            'description_logiciel' => 'Système de gestion budgétaire et financière développé pour optimiser la gestion des finances publiques et privées.',
            'copyright_texte' => 'Tous droits réservés',
            'copyright_annee_debut' => 2026,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametres_fournisseur');
    }
};