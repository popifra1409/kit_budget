<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le responsable de mise en œuvre d'une action et d'une activité est un agent, pas un compte.
 *
 * `actions.responsable_id` et `activites.responsable_id` (ajoutés par
 * 2026_10_20_000001_add_cadre_logique_fields.php) pointent vers `users`. Or le cadre logique
 * étendu et les états isolés des actions impriment ces deux mentions — « Responsable de
 * l'action », « Responsable de l'activité » — sous le nom de la personne qui exécute le
 * crédit, et cette personne n'a presque jamais de login : 87 personnels, 15 comptes, 3
 * agents seulement possèdent un compte. La liste proposée au chargé de programmation était
 * donc une liste d'accès, pas une liste d'agents : choisir un responsable valait délivrer
 * une habilitation, et un agent sans compte était choix impossible.
 *
 * Le programme a déjà basculé (2026_11_12_000001_add_responsable_personnel_id_to_programmes_table.php,
 * task #43) ; ces deux-là suivent le même chemin, avec le même nom de colonne explicite,
 * `responsable_personnel_id`. Le mot « responsable » a deux sens dans le module — l'agent
 * qui met en œuvre, et le compte qui filtre une liste — une colonne qui dit vers quelle
 * table elle pointe est le seul moyen d'éviter qu'un futur lecteur joigne `users` dessus.
 *
 * Le sous-programme reste sur `responsable_id` -> `users`, volontairement : sa valeur n'est
 * pas qu'un libellé imprimé, c'est la clé de cloisonnement de la revue des libellés
 * (ArborescenceLibellesService:39) et de la matrice d'arrimage (MatriceArrimageService:50),
 * comparée à `$user->id` de la session. La remplacer demanderait de traduire chaque
 * connexion en personnel, et 84 agents sur 87 ne le peuvent pas.
 *
 * Le `nullOnDelete()` est décoratif, comme au #43 : `Personnel` est en SoftDeletes, donc
 * `$agent->delete()` est un UPDATE de `deleted_at` que PostgreSQL ne voit jamais passer.
 * C'est la lecture qui protège l'affichage — les `belongsTo` sont déclarées `withTrashed()`
 * pour qu'un agent retiré reste imprimable dans un état déjà diffusé.
 *
 * La suppression de l'ancienne colonne n'est pas aveugle : les valeurs sont d'abord
 * traduites par `personnels.user_id` (le seul lien documenté entre les deux populations),
 * et s'il reste une ligne dont le compte ne correspond à aucun agent, la migration refuse
 * au lieu d'effacer un nom. Sur la base de développement les deux colonnes sont vides ;
 * ce refus protège les bases où quelqu'un a déjà désigné.
 */
return new class extends Migration
{
    /** @var array<string, string> table => libellé du niveau, pour les messages */
    private const NIVEAUX = [
        'actions'  => 'action',
        'activites' => 'activité',
    ];

    public function up(): void
    {
        foreach (self::NIVEAUX as $table => $niveau) {
            Schema::table($table, function (Blueprint $schema) use ($niveau) {
                // Pas de `->after()` : PostgreSQL ne sait pas ordonner une colonne.
                $schema->foreignId('responsable_personnel_id')
                    ->nullable()
                    ->comment("Agent responsable de la mise en œuvre de l'{$niveau} — table personnels, pas users")
                    ->constrained('personnels')
                    ->nullOnDelete();
            });

            $this->traduire($table, $niveau);

            Schema::table($table, fn (Blueprint $schema) => $schema->dropConstrainedForeignId('responsable_id'));
        }
    }

    public function down(): void
    {
        foreach (self::NIVEAUX as $table => $niveau) {
            Schema::table($table, function (Blueprint $schema) {
                $schema->foreignId('responsable_id')
                    ->nullable()
                    ->comment("Compte responsable de la mise en œuvre — remplacé par responsable_personnel_id")
                    ->constrained('users')
                    ->nullOnDelete();
            });

            // Le chemin inverse est perdu par construction : un agent sans compte n'a pas
            // d'identifiant `users` auquel rattacher une valeur.
            Schema::table($table, fn (Blueprint $schema) => $schema->dropConstrainedForeignId('responsable_personnel_id'));
        }
    }

    /**
     * `responsable_id` (users) -> `responsable_personnel_id` (personnels), par le compte que
     * l'agent porte déjà. Refuse si un nom disparaîtrait.
     *
     * La requête ignore `deleted_at` : un agent retiré qui était déjà désigné reste le bon
     * destinataire, les `belongsTo` l'imprimant ensuite en `withTrashed()`.
     */
    private function traduire(string $table, string $niveau): void
    {
        DB::update(
            "update {$table} t
                set responsable_personnel_id = p.id
              from personnels p
              where t.responsable_id is not null
                and p.user_id = t.responsable_id"
        );

        $orphelins = DB::table("{$table} as t")
            ->join('users as u', 'u.id', '=', 't.responsable_id')
            ->whereNotNull('t.responsable_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('personnels as p')
                ->whereColumn('p.user_id', 't.responsable_id'))
            ->select('t.code', 'u.name')
            ->get();

        if ($orphelins->isNotEmpty()) {
            throw new RuntimeException(
                "Côté {$niveau}, " . $orphelins->count() . ' ligne(s) désignent un compte qui'
                . " n'est le login d'aucun agent : "
                . $orphelins->map(fn($l) => "{$l->code} ({$l->name})")->implode(', ')
                . '. La migration refuse d effacer ces noms. Deux issues : rattacher le compte'
                . ' à son agent (personnels.user_id) puis rejouer la migration, ou laisser'
                . ' faire la désignation à la main dans les écrans après bascule — dans ce cas'
                . ' retire ces valeurs au préalable.'
            );
        }
    }
};
