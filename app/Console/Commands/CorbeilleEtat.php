<?php

namespace App\Console\Commands;

use App\Traits\HasCorbeille;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Inventaire de la corbeille, en lecture seule.
 *
 * `corbeille:etat` répond à « qu'est-ce qui a été supprimé ? » avant toute décision de
 * restauration : il marche modèle par modèle (les traits, pas les tables), parce que la
 * colonne `deleted_at` existe aussi sur des tables hors corbeille.
 *
 * Deux drapeaux coûtent peu à lever ici et changent la décision du geste suivant :
 *  - `anonymes` : lignes mortes SANS `deleted_by` ni jeton. Antérieures au câblage, elles
 *    reviennent seules (le trait ne ressuscite aucun descendant quand le jeton est null),
 *    mais personne ne peut dire qui les a jetées.
 *  - `sans_parent` : lignes dont l'ascendant est lui-même en corbeille ou n'existe plus.
 *    Les faire revenir les remet vivantes dans un dossier mort — visible nulle part.
 *
 * L'option `--redresser` n'existe pas : la restauration est un autre geste
 * (`corbeille:restaurer`), avec ses refus.
 */
class CorbeilleEtat extends Command
{
    protected $signature = 'corbeille:etat
                            {--table= : une seule table, ex. taches}
                            {--exercice= : restreindre à un exercice (id)}
                            {--detail=5 : lignes affichées par modèle (0 = compteurs seuls)}
                            {--large : afficher aussi les modèles vides}';

    protected $description = 'Inventaire en lecture seule de tout ce que la corbeille contient';

    public function handle(): int
    {
        $modeles = $this->modelesCables();
        if (!$modeles) {
            $this->error('Aucun modèle ne utilise HasCorbeille — chargement des modèles vide.');
            return self::FAILURE;
        }

        $totalMorts = 0;
        $totalAnonymes = 0;
        $sansParent = [];

        $this->line('Modèles parcourus : ' . count($modeles));
        $this->newLine();

        foreach ($modeles as $modele) {
            $table = (new $modele)->getTable();
            $requete = $modele::onlyTrashed()->withoutGlobalScopes();

            if ($this->option('exercice') && DB::getSchemaBuilder()->hasColumn($table, 'exercice_id')) {
                $requete->where('exercice_id', (int) $this->option('exercice'));
            }

            $lignes = (clone $requete)->orderByDesc('deleted_at')->get();
            $morts = $lignes->count();
            if (!$morts && !$this->option('large')) {
                continue;
            }

            $anonymes = $lignes->whereNull('corbeille_group')->count();
            $avecJeton = $morts - $anonymes;
            $totalMorts += $morts;
            $totalAnonymes += $anonymes;

            $this->info("{$table} ({$modele})");
            $this->line(sprintf(
                '  %d en corbeille — %d avec jeton (traçables), %d anonymes (avant câblage)',
                $morts,
                $avecJeton,
                $anonymes
            ));

            if ($avecJeton) {
                $parJeton = $lignes->filter(fn($l) => $l->corbeille_group !== null)
                    ->groupBy('corbeille_group');
                $famille = $parJeton->map->count()->sortDesc()->take(3)->all();
                $this->line('  familles : ' . $parJeton->count()
                    . ' jeton(s), les plus peuplées ' . implode(', ', $famille) . ' lignes');
            }

            $detail = (int) $this->option('detail');
            foreach ($lignes->take($detail) as $ligne) {
                $qui = $ligne->deleted_by
                    ? 'par #' . $ligne->deleted_by
                    : 'auteur inconnu';
                $ceQui = $this->intitule($ligne);
                $orpheline = $this->ascendantPerdu($ligne);
                if ($orpheline !== null) {
                    $sansParent[] = "{$table}:{$ligne->getKey()}";
                    $orpheline = " ⚠️ ascendant {$orpheline}";
                } else {
                    $orpheline = '';
                }
                $this->line(sprintf(
                    '    #%-6s %s — %s, %s%s',
                    $ligne->getKey(),
                    mb_substr($ligne->deleted_at->format('Y-m-d H:i'), 0, 16),
                    mb_substr($ceQui, 0, 58),
                    $qui,
                    $orpheline
                ));
            }
            if ($morts > $detail) {
                $this->line('    … ' . ($morts - $detail) . ' de plus (--detail=0 pour tout)');
            }
            $this->newLine();
        }

        $this->newLine();
        $this->line("^corbeille_modeles=" . count($modeles));
        $this->line("^corbeille_total_morts={$totalMorts}");
        $this->line("^corbeille_anonymes={$totalAnonymes}");
        $this->line('^corbeille_sans_parent=' . (count($sansParent) ? implode(',', array_slice($sansParent, 0, 12)) : 'aucun'));
        if ($totalMorts) {
            $this->line('');
            $this->line('Rien n\'a été écrit. Pour faire revenir une ligne, voyez corbeille:restaurer.');
        }

        return self::SUCCESS;
    }

    /**
     * Tous les modèles du projet qui utilisent le trait de corbeille.
     *
     * `public` : `corbeille:restaurer` relit cette liste, l'intitulé et l'ascendant ici plutôt
     * que d'en recopier la logique — deux versions du même inventaire finiraient par se
     * contredire sur ce qui est restaurable.
     */
    public function modelesCables(): array
    {
        $trouve = [];
        foreach (glob(app_path('Models/*.php')) as $fichier) {
            $classe = 'App\\Models\\' . basename($fichier, '.php');
            if (!class_exists($classe)) {
                continue;
            }
            if (!is_subclass_of($classe, Model::class)) {
                continue;
            }
            if (!in_array(HasCorbeille::class, class_uses_recursive($classe), true)) {
                continue;
            }
            if (!in_array(SoftDeletes::class, class_uses_recursive($classe), true)) {
                // `onlyTrashed()` n'est pas une méthode du modèle : c'est une macro du
                // query builder posée par SoftDeletes. method_exists() répond false sur les
                // 71 modèles câblés, et la commande s'annonce vide.
                continue;
            }
            $table = (new $classe)->getTable();
            if ($this->option('table') && $this->option('table') !== $table) {
                continue;
            }
            if (!DB::getSchemaBuilder()->hasColumn($table, 'corbeille_group')) {
                continue;
            }
            $trouve[] = $classe;
        }
        sort($trouve);
        return $trouve;
    }

    /** Un libellé qui ne casse pas : les modèles du parc n'ont pas tous la même colonne. */
    public function intitule(Model $ligne): string
    {
        foreach (['numero', 'code', 'libelle', 'objet', 'nom_complet', 'nom', 'name', 'titre', 'designation'] as $colonne) {
            $valeur = $ligne->getAttribute($colonne);
            if (is_string($valeur) && trim($valeur) !== '') {
                return $valeur;
            }
        }
        return mb_substr((string) json_encode($ligne->only(['id', 'statut', 'type']), JSON_UNESCAPED_UNICODE), 0, 58);
    }

    /**
     * L'ascendant déclaré par le modèle est-il vivant ? On ne devine pas l'ascendant :
     * on regarde les BelongsTo du modèle qui pointent sur une table elle aussi câblée.
     * Renvoie la relation perdue, null si rien ne manque.
     */
    public function ascendantPerdu(Model $ligne): ?string
    {
        foreach ($this->appartenance($ligne) as $nom => [$classe, $cle]) {
            if ($cle === null) {
                continue;
            }
            if (!$classe::withTrashed()->whereKey($cle)->exists()) {
                return $nom . ' (#' . $cle . ', table absente)';
            }
            if ($classe::withTrashed()->whereKey($cle)->onlyTrashed()->exists()) {
                return $nom . ' (#' . $cle . ', en corbeille)';
            }
        }
        return null;
    }

    /** BelongsTo → [classe, clé foreign] pour ce modèle. Cache par classe. */
    protected function appartenance(Model $ligne): array
    {
        static $cache = [];
        $classe = get_class($ligne);
        if (isset($cache[$classe])) {
            return $cache[$classe];
        }

        $methodes = array_map(
            fn(\ReflectionMethod $m) => $m->getName(),
            (new \ReflectionClass($classe))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        $carte = [];
        foreach ($methodes as $methode) {
            if (!method_exists($classe, $methode) || in_array($methode, ['boot', 'booted'], true)) {
                continue;
            }
            $retour = (new \ReflectionMethod($classe, $methode))->getReturnType();
            // BelongsTo EXACTEMENT : 'Relations\BelongsTo' est aussi le début de
            // 'Relations\BelongsToMany', dont getForeignKeyName() n'existe pas.
            if (!$retour || $retour->getName() !== BelongsTo::class) {
                continue;
            }
            if (str_starts_with($methode, 'get') || str_starts_with($methode, 'scope')) {
                continue;
            }
            $relation = $ligne->{$methode}();
            $classeCible = get_class($relation->getRelated());
            if (!in_array(HasCorbeille::class, class_uses_recursive($classeCible), true)) {
                continue;
            }
            $carte[$methode] = [$classeCible, $ligne->getAttribute($relation->getForeignKeyName())];
        }

        return $cache[$classe] = $carte;
    }
}
