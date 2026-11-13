<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le responsable d'un sous-programme est un agent, et le cloisonnement le suit.
 *
 * Les trois niveaux enfants ont basculé (2026_11_12_000001 pour `programmes`,
 * 2026_11_12_000002 pour `actions` et `activites`). Celui-ci restait sur `users`, et c'est
 * celui que l'écran dénonce : le formulaire du sous-programme, ouvert depuis le plan
 * stratégique, proposait « Responsable de mise en œuvre » dans une liste de comptes. Or un
 * responsable de sous-programme est nommé dans un état, pas connecté : 94 agents, 15 comptes,
 * 3 agents seulement portent un `personnels.user_id`.
 *
 * La colonne ne sert pas qu'à l'affichage — `responsable_id` est aussi la clé de cloisonnement
 * de deux écrans (`ArborescenceLibellesService::sousProgrammesVisibles()` et
 * `MatriceArrimageService::sousProgrammesVisibles()`), qui comparait la valeur à `$user->id`
 * de la session. 2026_11_12_000002 avait renoncé à y toucher pour cette raison : traduire
 * chaque connexion en agent semblait impossible. Les comptes disent le contraire — les huit
 * comptes qui voient tous les sous-programmes les voient par `view_all_arborescence_libelles`
 * et `view_all_matrice_arrimage`, pas par `responsable_id` ; les sept restants n'y sont
 * désignés par personne, et aucun n'a de fiche agent. Le cloisonnement par compte ne protège
 * aujourd'hui que des gens qui n'ont rien à cloisonner, tandis que les deux écrans impriments,
 * eux, un nom d'agent. Un seul des deux sens de « responsable » mérite une colonne.
 *
 * Le filtre devient donc `responsable_personnel_id = <l'agent du compte>`. Un compte sans
 * fiche agent ne cloisonne sur rien et ne voit rien : c'est le comportement actuel, et le
 * seul honnête — la liste vide se comprend, la liste complète serait une fuite.
 *
 * `nullOnDelete()` ne protège pas ce qu'on croirait (voir la migration des programmes) :
 * `Personnel` est en SoftDeletes, donc `$agent->delete()` est un UPDATE de `deleted_at` que
 * PostgreSQL n'évalue jamais. C'est la lecture qui protège l'affichage — `responsable()` est
 * déclarée `withTrashed()`.
 *
 * L'ancienne colonne n'est pas supprimée à l'aveugle : les valeurs sont d'abord traduites par
 * `personnels.user_id`, et s'il reste un compte qui n'est le login d'aucun agent, la migration
 * refuse. Elle refusera sur la base de développement — SP-410 désigne `users.id = 10`
 * (WADJIRI Vincent) alors que l'agent s'appelle WADJIRI VINCENT (`personnels.id = 32`,
 * `user_id` NULL) et qu'il existe même un doublon inactif de ce nom (id 31). C'est exactement
 * pourquoi la traduction ne cherche pas par le nom : le rapprochement est à faire par la
 * main, une fois, sur la bonne fiche. Le message du refus donne les deux issues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            // Pas de `->after()` : PostgreSQL ne sait pas ordonner une colonne.
            $table->foreignId('responsable_personnel_id')
                ->nullable()
                ->comment('Agent responsable de la mise en œuvre du sous-programme — table personnels, pas users')
                ->constrained('personnels')
                ->nullOnDelete();
        });

        $this->traduire();

        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
        });
    }

    public function down(): void
    {
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->foreignId('responsable_id')
                ->nullable()
                ->comment('Compte responsable du sous-programme — remplacé par responsable_personnel_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Le chemin inverse est perdu par construction : un agent sans compte n'a pas
        // d'identifiant `users` auquel rattacher une valeur.
        Schema::table('sous_programmes_ep', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_personnel_id');
        });
    }

    /**
     * `responsable_id` (users) -> `responsable_personnel_id` (personnels) par le compte que
     * l'agent porte déjà, puis refus si un nom disparaîtrait.
     *
     * La requête ignore `deleted_at` : un agent retiré déjà désigné reste le bon destinataire,
     * la relation l'imprimant ensuite en `withTrashed()`.
     */
    private function traduire(): void
    {
        DB::update(
            'update sous_programmes_ep t
                set responsable_personnel_id = p.id
              from personnels p
              where t.responsable_id is not null
                and p.user_id = t.responsable_id'
        );

        $orphelins = DB::table('sous_programmes_ep as t')
            ->join('users as u', 'u.id', '=', 't.responsable_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('personnels as p')
                ->whereColumn('p.user_id', 't.responsable_id'))
            ->select('t.code', 'u.name')
            ->get();

        if ($orphelins->isNotEmpty()) {
            throw new RuntimeException(
                $orphelins->count() . ' sous-programme(s) désignent un compte qui n\'est le login'
                . ' d\'aucun agent : '
                . $orphelins->map(fn($l) => "{$l->code} ({$l->name})")->implode(', ')
                . '. La migration refuse d\'effacer ces noms. Deux issues : rattacher chaque'
                . ' compte à sa fiche agent — update personnels set user_id = <id du compte>'
                . ' where id = <id de l\'agent>, en vérifiant le matricule car des homonymes'
                . ' existent — puis rejouer la migration ; ou effacer ces désignations'
                . ' (update sous_programmes_ep set responsable_id = null) et les refaire à'
                . ' l\'écran après la bascule, où la liste sera celle des personnels.'
            );
        }
    }
};
