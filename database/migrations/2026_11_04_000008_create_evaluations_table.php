<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marchés publics — étape D 1/2 : la grille d'évaluation des offres.
 *
 * Le paramétrage (étape A) a posé `elements_evaluation_dao` : les trois volets du
 * schéma — pièce administrative, pièce financière, description technique — chacun
 * avec son barème. Cette table est la note : une ligne = une offre notée sur une
 * étape du volet, et rien d'autre.
 *
 * Trois partis pris :
 *
 * 1. Pas de statut, pas de soft delete. Une note n'est pas un document du circuit :
 *    elle se corrige (la ligne garde sa trace d'auteur et de date, et l'observateur
 *    d'audit écrit l'ancien et le nouveau), elle ne se « valide » pas et ne se
 *    retire pas du dossier. Une correction d'évaluation se lit dans le journal.
 *
 * 2. Une note par offre et par étape, contrainte d'unicité pleine : ici rien
 *    n'est soft-deleted, donc pas de `WHERE deleted_at IS NULL` à poser. Deux notes
 *    de la même offre sur la même étape voudraient dire deux mains sur la même
 *    ligne du PV, et le classement ne saurait laquelle choisir.
 *
 * 3. `note` est NOT NULL et bornée en bas par la base, bornée en haut par le modèle :
 *    `bareme_max` vit dans une autre table, et un CHECK ne lit pas une autre table.
 *    Le contrôle est donc dans Evaluation, sur tous les chemins d'écriture.
 *
 * Le barème de l'étape n'est pas recopié ici : une note se relit à 12/20 avec le
 * barème du jour. C'est le référentiel qui fait foi, et c'est pour ça que la clé
 * étrangère est `restrictOnDelete` — une étape déjà notée ne se supprime pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offre_id')->constrained('offres')->cascadeOnDelete()
                ->comment('L\'offre notée ; ses notes n\'ont de sens qu\'avec elle');
            $table->foreignId('element_evaluation_dao_id')
                ->constrained('elements_evaluation_dao')->restrictOnDelete()
                ->comment('L\'étape du DAO notée, avec son barème — le référentiel fait foi');

            $table->decimal('note', 6, 2)
                ->comment('≤ elements_evaluation_dao.bareme_max, contrôlé dans le modèle');
            $table->text('commentaire')->nullable()
                ->comment('Ce que l\'évaluateur motive : pièce manquante, hors format, réserve');

            // Une note anonyme n'est pas une note : l'audit demande quelle main l'a
            // portée, et à quelle date. Les deux colonnes sont écrites par le modèle.
            $table->foreignId('evalue_par_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('date_evaluation');

            $table->timestamps();

            $table->unique(['offre_id', 'element_evaluation_dao_id']);
            $table->index('element_evaluation_dao_id');
            $table->index('offre_id');
        });

        // Pas de note négative : le barème commence à zéro.
        DB::statement('
            ALTER TABLE evaluations
                ADD CONSTRAINT evaluations_note_positive_check
                CHECK (note >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
