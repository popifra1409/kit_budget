<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Faire revenir des lignes de la corbeille.
 *
 * `corbeille:etat` répond à « quoi ? » ; ici on décide. Le geste est SEC par défaut et
 * n'écrit qu'avec `--appliquer`, parce qu'une restauration n'est pas neutre : le trait
 * `HasCorbeille::restored()` ramène tout ce qui porte le même jeton. La commande montre
 * d'abord l'entraîné, puis écrit si on le lui demande.
 *
 * Trois refus, dans l'ordre :
 *  1. table que la corbeille ne connaît pas (ni le trait, ni la colonne `corbeille_group`) ;
 *  2. ligne déjà vivante ou absente — on ne restaure pas ce qui n'est pas mort ;
 *  3. ascendant perdu : le faire revenir l'attacherait à un dossier mort, donc à rien du
 *     tout à l'écran. Refus, pas avertissement — le dégât ne se voit qu'après.
 *
 * Le 3 n'a pas besoin d'échappatoire : le parent se restaure AVANT l'enfant, et quand il est
 * tracé `restored()` le fait déjà tout seul. D'où l'absence de `--forcer`.
 *
 * Chaque ligne fait sa propre transaction : en Postgres, une seule clé unique qui coince dans
 * une transaction commune annulerait aussi les restaurations déjà réussies.
 */
class CorbeilleRestaurer extends Command
{
    protected $signature = 'corbeille:restaurer
                            {table : ex. taches}
                            {--id=* : identifiant(s) à faire revenir — répétez le drapeau}
                            {--appliquer : écrire ; sans ce drapeau la commande se contente de montrer}';

    protected $description = 'Faire revenir une ou plusieurs lignes de la corbeille (sec par défaut)';

    public function handle(): int
    {
        $table = (string) $this->argument('table');

        // Les clés ne sont pas toujours des entiers (des modèles du parc sont UUID) : on les
        // garde telles quelles, `whereKey()` les lie, et un identifiant inventé tombe dans le
        // refus « ABSENTE DE LA BASE » au lieu de devenir #0.
        $ids = array_values(array_unique(array_filter(
            array_map(fn($v) => trim((string) $v), (array) $this->option('id')),
            fn(string $v) => $v !== '' && strlen($v) <= 64
        )));

        if (!$ids) {
            $this->error('Rien à faire revenir : donnez au moins un --id= (ex. --id=35 --id=23).');
            return self::FAILURE;
        }

        $plan = $this->plan($table, $ids);

        if ($plan['erreur'] !== null) {
            $this->error($plan['erreur']);
            return self::FAILURE;
        }

        $modele = $plan['modele'];
        $aRamener = $plan['aRamener'];
        $refus = $plan['refus'];
        $descendants = $plan['descendants'];
        $enfantsRestes = $plan['enfantsRestes'];
        $appliquer = (bool) $this->option('appliquer');

        $this->info($appliquer
            ? 'ÉCRITURE — ' . $modele . ' : #' . implode(', #', $ids)
            : 'SEC — rien ne sera écrit (' . count($ids) . ' ids demandés sur ' . $modele . ')');
        $this->line('');

        foreach ($aRamener as $c) {
            $this->line('  ✓ ' . $c['libelle']);
        }

        foreach ($refus as $r) {
            $this->line('  ✗ ' . $r);
        }

        $faite = [];
        $echouees = [];

        if ($aRamener && $appliquer) {
            $this->newLine();
            $ecriture = $this->ecrire($aRamener);
            $faite = $ecriture['faite'];
            $echouees = $ecriture['echouees'];
            foreach ($ecriture['libelles'] as $libelle) {
                $this->line('  ↺ rendue : ' . $libelle);
            }
            foreach ($echouees as $m) {
                $this->line('  ✗ écriture refusée : ' . $m);
            }
        }

        $this->newLine();
        $this->line('^restaurer_table=' . $table);
        $this->line('^restaurer_modele=' . $modele);
        $this->line('^restaurer_sec=' . ($appliquer ? 0 : 1));
        $this->line('^restaurer_demandes=' . count($ids));
        $this->line('^restaurer_premontables=' . count($aRamener));
        $this->line('^restaurer_faite=' . count($faite)
            . ($faite ? ':' . implode(',', array_slice($faite, 0, 12)) : ''));
        // Les marqueurs restent des nombres : le détail des refus est déjà imprimé ligne par
        // ligne, et un témoin qui doit tordre une chaîne pour lire un compteur ne prouve plus
        // le compteur.
        $this->line('^restaurer_refuses=' . count($refus));
        $this->line('^restaurer_ecritures_refusees=' . count($echouees));
        $this->line('^restaurer_jeton_mortes=' . array_sum($descendants));
        $this->line('^restaurer_enfants_restes=' . ($enfantsRestes ? $this->resume($enfantsRestes) : 'aucun'));

        if ($aRamener && !$appliquer) {
            $this->newLine();
            $this->line('Aucune écriture. Reprenez avec --appliquer pour faire revenir '
                . count($aRamener) . ' ligne(s)'
                . (array_sum($descendants) ? ' et ' . array_sum($descendants) . ' ligne(s) entraînée(s).' : '.'));
        }

        if ($faite && is_a($modele, \App\Models\Engagement::class, true)) {
            $this->line('');
            $this->warn('Engagements rendus : les crédits ne suivent pas tout seuls — vérifiez avec php artisan donnees:correctifs.');
        }

        if (!$aRamener) {
            return self::FAILURE;
        }

        return $echouees ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ce que la restauration FERAIT : les lignes à ramener avec leur libellé, les refus motif par
     * motif, ce que le jeton entraîne et ce qui reste mort sous le parent.
     *
     * `public` et source unique de décision : l'écran Corbeille montre ce plan avant de proposer le
     * geste. Une seconde implémentation côté écran finirait par contredire la console sur ce qui est
     * restaurable — et le seul désaccord qui compte, c'est « accepté ici, refusé là ».
     *
     * Rien n'est écrit ici : `ecrire()` prend le résultat, côté console comme côté écran.
     */
    public function plan(string $table, array $ids): array
    {
        $audit = $this->audit($table);
        $modeles = $audit->modelesCables();

        $plan = [
            'table' => $table,
            'modele' => null,
            'aRamener' => [],
            'refus' => [],
            'descendants' => [],
            'enfantsRestes' => [],
            'erreur' => null,
        ];

        if (count($modeles) !== 1) {
            $plan['erreur'] = count($modeles) === 0
                ? "La corbeille ne connaît pas « {$table} » : aucun modèle câblé (HasCorbeille + colonne corbeille_group) ne la porte."
                : count($modeles) . " modèles câblés portent la table « {$table} » — impossible de choisir à votre place.";
            return $plan;
        }

        $modele = $modeles[0];
        $plan['modele'] = $modele;

        $morts = $modele::onlyTrashed()->withoutGlobalScopes()->whereKey($ids)->get()
            ->keyBy(fn(Model $l) => (string) $l->getKey());

        foreach ($ids as $id) {
            $ligne = $morts->get((string) $id);

            if (!$ligne) {
                $plan['refus'][] = "#{$id} " . ($modele::withoutGlobalScopes()->whereKey($id)->exists()
                    ? 'DÉJÀ VIVANTE — rien à restaurer'
                    : 'ABSENTE DE LA BASE');
                continue;
            }

            $orpheline = $audit->ascendantPerdu($ligne);
            if ($orpheline !== null) {
                $plan['refus'][] = "#{$id} ascendant {$orpheline} — à faire revenir d’abord";
                continue;
            }

            $libelle = sprintf('#%-7s %s — %s', $id,
                $ligne->deleted_at ? mb_substr($ligne->deleted_at->format('Y-m-d H:i'), 0, 16) : '?',
                mb_substr($audit->intitule($ligne), 0, 58));

            // Le jeton dit ce que le trait entraînera. Une ligne anonyme (antérieure au
            // câblage) revient seule : `restored()` remonte par le jeton et s'arrête quand il
            // est null — c'est exactement ce qui rend ses 3 tâches restaurables aujourd'hui.
            $famille = $ligne->corbeille_group ? $this->famille($ligne->corbeille_group) : [];
            if ($famille !== []) {
                foreach ($famille as $t => $n) {
                    $plan['descendants'][$t] = ($plan['descendants'][$t] ?? 0) + $n;
                }
                $libelle .= ' [tracée ' . mb_substr((string) $ligne->corbeille_group, 0, 8)
                    . ' → ' . array_sum($famille) . ' ligne(s) morte(s) du même jeton, dont '
                    . ($famille[$table] ?? 0) . ' sur ' . $table . ']';
            }

            // Dans les deux cas : ce qui meurt sous ce parent SANS partager son jeton ne
            // revient pas avec lui. Un parent tracé ramène les siens, pas l'enfant jeté à la
            // main la semaine dernière — l'avertir ici évite le « ma tâche est revenue sans
            // ses activités ».
            $restes = $this->enfantsRestesMorts($ligne);
            foreach ($restes as $nom => $n) {
                $plan['enfantsRestes'][$nom] = ($plan['enfantsRestes'][$nom] ?? 0) + $n;
            }
            if ($restes !== []) {
                $libelle .= ' — ⚠️ restent morts sous ce parent (jeton différent) : '
                    . $this->resume($restes);
            }

            $plan['aRamener'][] = ['ligne' => $ligne, 'libelle' => $libelle];
        }

        return $plan;
    }

    /**
     * Le geste : une transaction PAR ligne. En Postgres, une transaction commune et une seule clé
     * unique qui coince annulerait aussi les restaurations déjà réussies.
     */
    public function ecrire(array $aRamener): array
    {
        $faite = [];
        $libelles = [];
        $echouees = [];

        foreach ($aRamener as $c) {
            try {
                DB::transaction(fn() => $c['ligne']->restore());
                $faite[] = (string) $c['ligne']->getKey();
                $libelles[] = $c['libelle'];
            } catch (QueryException $e) {
                $echouees[] = '#' . $c['ligne']->getKey() . ' — '
                    . mb_substr((string) preg_replace('/\s+/', ' ', $e->getMessage()), 0, 110);
            }
        }

        return ['faite' => $faite, 'libelles' => $libelles, 'echouees' => $echouees];
    }

    /**
     * L'inventaire des modèles câblés, l'intitulé et l'ascendant sont déjà écrits dans
     * `corbeille:etat`. Plutôt que de les recopier — deux inventaires finissent par s'écouter
     * l'un l'autre et se contredire sur ce qui est restaurable —, on branche une instance de
     * cette commande sur CETTE table : son propre filtre `--table` fait le tri.
     */
    private function audit(?string $table): CorbeilleEtat
    {
        $audit = new CorbeilleEtat();
        $audit->setLaravel($this->laravel);
        $audit->setInput(new ArrayInput(
            array_filter(
                ['--table' => $table === '' ? null : $table, '--detail' => 0],
                fn($v) => $v !== null
            ),
            $audit->getDefinition()
        ));
        return $audit;
    }

    /** Lignes mortes portant ce jeton, table par table, sur tout le parc câblé. */
    private function famille(string $jeton): array
    {
        $parTable = [];
        foreach ($this->audit(null)->modelesCables() as $modele) {
            $n = $modele::onlyTrashed()->withoutGlobalScopes()
                ->where('corbeille_group', $jeton)->count();
            if ($n) {
                $parTable[(new $modele)->getTable()] = $n;
            }
        }
        return $parTable;
    }

    /**
     * Ce qui reste mort sous ce parent quand lui revient seul : les enfants jetés à la main
     * portent un autre jeton (ou aucun) et ne suivent pas la restauration — décision Q2 du
     * trait. Avertir ici, c'est éviter le « ma tâche est revenue sans ses activités ».
     */
    private function enfantsRestesMorts(Model $ligne): array
    {
        $restes = [];
        foreach ($ligne::corbeilleEnfants() as $nom) {
            if (!method_exists($ligne, $nom)) {
                continue;
            }
            $relation = $ligne->{$nom}();
            if (!$relation instanceof Relation) {
                continue;
            }
            if (!in_array(SoftDeletes::class, class_uses_recursive(get_class($relation->getRelated())), true)) {
                continue;
            }

            $requete = $relation->onlyTrashed()->withoutGlobalScopes();

            // Les lignes du JETON DU PARENT reviennent avec lui : ce ne sont pas des restes.
            // Sans cette coupe, la commande annoncerait le retour de ce qui revient déjà.
            if ($ligne->corbeille_group
                && DB::getSchemaBuilder()->hasColumn($relation->getRelated()->getTable(), 'corbeille_group')) {
                $requete->where(function ($q) use ($ligne) {
                    $q->whereNull('corbeille_group')
                        ->orWhere('corbeille_group', '!=', $ligne->corbeille_group);
                });
            }

            $morts = $requete->count();
            if ($morts) {
                $restes[$nom] = $morts;
            }
        }
        return $restes;
    }

    private function resume(array $parCles): string
    {
        return implode(', ', array_map(
            fn($cle, $n) => "{$cle}:{$n}",
            array_keys($parCles),
            array_values($parCles)
        ));
    }
}
