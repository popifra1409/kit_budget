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
        Schema::create('nomenclature_budgetaire', function (Blueprint $table) {
            $table->id();

            // Informations principales
            $table->string('code', 20)->comment('Code de la nomenclature (ex: 601, 601300, 722, 722200)');
            $table->string('libelle')->comment('Libellé de la ligne budgétaire');

            // Classe comptable et type
            $table->enum('classe', ['6', '7'])->comment('Classe comptable : 6=Dépenses (Charges), 7=Recettes (Produits)');
            $table->enum('type', ['depense', 'recette'])->comment('Type : depense (classe 6) ou recette (classe 7)');

            // Niveau hiérarchique
            $table->enum('niveau', ['classe', 'compte', 'sous_compte', 'ligne'])->comment('Niveau hiérarchique dans le plan comptable');

            // Hiérarchie (auto-référence)
            $table->foreignId('parent_id')->nullable()
                ->constrained('nomenclature_budgetaire')
                ->onDelete('cascade')
                ->comment('ID du parent (pour la hiérarchie)');

            // ========================================
            // SYSTÈME D'HISTORISATION
            // ========================================

            // Période de validité
            $table->date('date_debut_validite')->comment('Date de début de validité de cette version');
            $table->date('date_fin_validite')->nullable()->comment('Date de fin de validité (NULL = en cours)');

            // Traçabilité des modifications
            $table->string('code_precedent', 20)->nullable()->comment('Code précédent si modification du code');
            $table->foreignId('version_precedente_id')->nullable()
                ->constrained('nomenclature_budgetaire')
                ->onDelete('set null')
                ->comment('Référence vers la version précédente de cette nomenclature');

            $table->integer('version')->default(1)->comment('Numéro de version de cette nomenclature');

            // Raison du changement
            $table->text('motif_modification')->nullable()->comment('Raison de la modification de la nomenclature');

            // Utilisateur ayant effectué la modification
            $table->foreignId('modifie_par')->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Utilisateur ayant modifié cette nomenclature');

            // ========================================
            // FIN SYSTÈME D'HISTORISATION
            // ========================================

            // Métadonnées
            $table->integer('ordre')->default(0)->comment('Ordre d\'affichage');
            $table->boolean('actif')->default(true)->comment('Nomenclature active ou non');

            $table->timestamps();
            $table->softDeletes()->comment('Soft delete pour archivage');

            // Index pour optimiser les requêtes
            $table->index('code');
            $table->index('parent_id');
            $table->index('classe');
            $table->index('type');
            $table->index(['classe', 'type']);
            $table->index(['type', 'niveau']);

            // Index pour l'historisation
            $table->index('date_debut_validite');
            $table->index('date_fin_validite');
            $table->index(['code', 'date_debut_validite', 'date_fin_validite']);
            $table->index('version_precedente_id');
            $table->index('version');

            // Contrainte unique : un même code peut exister plusieurs fois avec des périodes différentes
            // Mais ne peut pas avoir deux périodes qui se chevauchent
            $table->unique(['code', 'date_debut_validite'], 'unique_code_date_debut');
        });

        // Commentaires sur la table
        DB::statement("COMMENT ON TABLE nomenclature_budgetaire IS 'Structure hiérarchique de la nomenclature comptable budgétaire avec historisation - Classe 6 (Dépenses) et Classe 7 (Recettes)'");

        // Contrainte : classe et type doivent correspondre
        DB::statement("ALTER TABLE nomenclature_budgetaire ADD CONSTRAINT check_classe_type CHECK (
            (classe = '6' AND type = 'depense') OR 
            (classe = '7' AND type = 'recette')
        )");

        // Contrainte : date_fin_validite doit être après date_debut_validite
        DB::statement("ALTER TABLE nomenclature_budgetaire ADD CONSTRAINT check_dates_validite CHECK (
            date_fin_validite IS NULL OR date_fin_validite > date_debut_validite
        )");

        // Fonction pour vérifier qu'il n'y a pas de chevauchement de périodes pour un même code
        DB::statement("
            CREATE OR REPLACE FUNCTION check_no_overlap_nomenclature()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Vérifier qu'il n'existe pas déjà une période active pour ce code
                IF EXISTS (
                    SELECT 1 
                    FROM nomenclature_budgetaire 
                    WHERE code = NEW.code 
                    AND id != COALESCE(NEW.id, 0)
                    AND deleted_at IS NULL
                    AND (
                        -- Cas 1 : La nouvelle période commence pendant une période existante
                        (NEW.date_debut_validite BETWEEN date_debut_validite AND COALESCE(date_fin_validite, '9999-12-31'))
                        OR
                        -- Cas 2 : La nouvelle période se termine pendant une période existante
                        (COALESCE(NEW.date_fin_validite, '9999-12-31') BETWEEN date_debut_validite AND COALESCE(date_fin_validite, '9999-12-31'))
                        OR
                        -- Cas 3 : La nouvelle période englobe une période existante
                        (NEW.date_debut_validite <= date_debut_validite AND COALESCE(NEW.date_fin_validite, '9999-12-31') >= COALESCE(date_fin_validite, '9999-12-31'))
                    )
                ) THEN
                    RAISE EXCEPTION 'Le code % a déjà une nomenclature valide sur cette période', NEW.code;
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger pour vérifier le non-chevauchement
        DB::statement("
            CREATE TRIGGER trigger_check_no_overlap_nomenclature
            BEFORE INSERT OR UPDATE ON nomenclature_budgetaire
            FOR EACH ROW
            EXECUTE FUNCTION check_no_overlap_nomenclature();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les triggers et fonctions
        DB::statement("DROP TRIGGER IF EXISTS trigger_check_no_overlap_nomenclature ON nomenclature_budgetaire");
        DB::statement("DROP FUNCTION IF EXISTS check_no_overlap_nomenclature");

        Schema::dropIfExists('nomenclature_budgetaire');
    }
};
