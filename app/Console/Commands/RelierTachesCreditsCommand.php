<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\NomenclatureBudgetaire;
use App\Models\Service;
use App\Models\SousProgrammeEp;
use App\Models\Tache;
use App\Services\Planification\CadreLogiqueEtendu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Relie les tâches de la programmation aux comptes budgétaires, à partir d'un CSV.
 *
 * Le CSV est la saisie de l'agent : une ligne par tâche, avec `code_nomenclature`. Cette commande
 * n'écrit QUE le compte (et, s'ils sont renseignés, délai, guichet, service).
 *
 * Elle n'écrit aucun montant, et c'est voulu : le budget de l'exercice est adopté et
 * `verrouiller_taches_budget_adopte` est vrai, donc le modèle refuse tout AE/CP posé à la main sur
 * une tâche existante (`Tache::saving`). Les montants viennent des lignes du budget, par
 * `budget:synchroniser-taches`, qui répartit l'écart sur les tâches porteuses du même compte. Si
 * le CSV porte une colonne ae ou cp remplie, elle est annoncée « ignorée » — pas appliquée, pas tue.
 *
 * Trois issues par ligne, et le rapport les dit toutes :
 *  - refus : compte inconnu ou ambigu, tâche supprimée, mère déjà dotée de sous-tâches, exercice
 *    qui ne correspond pas, compte déjà posé sans --remplacer. Un seul refus et rien n'est écrit :
 *    une matrice à moitié reliée est pire qu'une matrice entière à relier demain.
 *  - déjà reliée au même compte : rien n'est fait, mais la ligne est comptée à part.
 *  - appliquée : le compte est posé, la tâche sort du hors-crédit de la matrice.
 *
 * Après les écritures et avant le commit, la commande se vérifie elle-même, par sous-programme
 * touché : le hors-crédit a baissé du nombre exact de lignes appliquées, la matrice a gagné ce même
 * nombre de lignes, et les totaux AE/CP n'ont pas bougé d'un centime. Si l'une de ces trois
 * promesses manque, tout est rollback — même sans --sans-ecrire.
 *
 * La sortie annonce aussi, pour chaque compte relié, ce que la synchronisation lui donnerait :
 * dotation libre, ou compte déjà dosé où une tâche à CP nul ne recevrait rien (le prorata se fait
 * sur les CP, SynchronisationTachesService:64). C'est le seul endroit où l'agent voit que sa ligne
 * est vraie mais non financée.
 */
class RelierTachesCreditsCommand extends Command
{
    protected $signature = 'planification:relier-credits
                            {fichier : CSV séparé par des points-virgules, avec une ligne d\'en-tête}
                            {--exercice= : Année de l\'exercice (par défaut : l\'exercice actif)}
                            {--remplacer : Autorise à changer un compte déjà posé}
                            {--sans-ecrire : Annoncer et vérifier sans rien enregistrer}';

    protected $description = "Relie les tâches de la programmation aux comptes du budget (aucun montant écrit)";

    public function handle(): int
    {
        $chemin = (string) $this->argument('fichier');
        if (!is_file($chemin) || !is_readable($chemin)) {
            $this->error("Fichier introuvable ou illisible : $chemin");
            return self::FAILURE;
        }

        $exercice = $this->option('exercice')
            ? Exercice::withoutGlobalScopes()->where('annee', (int) $this->option('exercice'))->first()
            : Exercice::getActif();

        if (!$exercice) {
            $this->error('Exercice introuvable — précise --exercice=AAAA.');
            return self::FAILURE;
        }

        [$lignes, $erreurs] = $this->lireCsv($chemin);
        if ($erreurs !== []) {
            foreach ($erreurs as $e) $this->error($e);
            $this->line('Le fichier n\'est pas exploitable en l\'état — rien n\'a été écrit.');
            return self::INVALID;
        }
        if ($lignes === []) {
            $this->warn('Aucune ligne de données dans le fichier.');
            return self::SUCCESS;
        }

        $this->info(sprintf('^exercice=%d | lignes_lues=%d', $exercice->annee, count($lignes)));

        $sansEcrire = (bool) $this->option('sans-ecrire');
        $remplacer  = (bool) $this->option('remplacer');

        // Phase 1 : tout contrôler avant d'écrire la première valeur.
        // withoutGlobalScopes() retire aussi la portée SoftDeletes : le compte mort doit rester
         // inconnu de la saisie, sinon l'import le poserait sur une tâche.
        $comptesParCode = NomenclatureBudgetaire::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('type', 'depense')->where('actif', true)->get()->groupBy('code');
        $services = Service::withoutGlobalScopes()->get()->keyBy('code');

        $prets = [];
        $refus = [];
        $dejaReliees = 0;

        foreach ($lignes as $numero => $ligne) {
            [$etat, $valeur] = $this->controler($ligne, $numero, $exercice, $comptesParCode, $services, $remplacer);
            if ($etat === 'pret') { $prets[] = $valeur; continue; }
            if ($etat === 'deja_reliee') { $dejaReliees++; continue; }
            $refus[] = $valeur;
        }

        if ($refus !== []) {
            $this->error(sprintf('^refus=%d', count($refus)));
            foreach ($refus as $r) $this->line("  $r");
            $this->line('Rien n\'a été écrit : corrige le fichier, puis relance.');
            return self::INVALID;
        }

        $this->info(sprintf('^a_relier=%d | deja_reliees=%d | refus=0', count($prets), $dejaReliees));

        $ignorees = collect($lignes)->filter(
            fn($l) => ($l['ae'] ?? '') !== '' || ($l['cp'] ?? '') !== ''
        )->count();
        if ($ignorees > 0) {
            $this->warn(sprintf('^colonnes_montants_ignorees=%d — le budget est adopté : les AE/CP '
                . 'viennent des lignes budgétaires, pas du CSV', $ignorees));
        }

        // Phase 2 : écrire, se vérifier, puis décider de garder ou rendre. Transaction explicite et
        // non DB::transaction() : la commande peut tourner dans la transaction d'un harnais de test,
        // et son annulation ne doit jamais remonter au-delà de son propre niveau (rollBack() de
        // Laravel roule vers le niveau cible, y compris hors de la closure appelante).
        $codage = 0;   // nombre de lignes réellement posées, pour le rapport final
        DB::beginTransaction();
        try {
            $avant = $this->instantanes($this->spConcernes($prets), $exercice);

            foreach ($prets as $p) {
                $tache = $p['tache'];
                $tache->nomenclature_id = $p['compte']->id;
                if ($p['delai']   !== null) $tache->delai = $p['delai'];
                if ($p['guichet'] !== null) $tache->guichet = $p['guichet'];
                if ($p['service'] !== null) $tache->service_id = $p['service']->id;
                $tache->save();
                $codage++;
            }

            $rapport = $this->verifier($avant, $prets, $exercice);
            $this->line($rapport['table']);
            foreach ($rapport['alertes'] as $a) $this->warn("  $a");

            if ($rapport['promesses_manquees'] !== []) {
                DB::rollBack();
                $this->error('^promesses_manquees=' . implode(' | ', $rapport['promesses_manquees']));
                $this->error('Tout a été annulé : la matrice n\'a pas reçu ce qu\'elle devait.');
                return self::INVALID;
            }

            if ($sansEcrire) {
                DB::rollBack();
                $this->warn('^ecritures=annulees (--sans-ecrire) — les chiffres ci-dessus sont ceux d\'un essai.');
            } else {
                DB::commit();
                $this->info('^ecritures=enregistrees');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->line(sprintf('^reliees=%d | deja_reliees=%d', $codage, $dejaReliees));
        $this->line('');
        $this->line('Ouvrir les montants de ces comptes :');
        $this->line('  php artisan budget:synchroniser-taches            (simulation, sans --appliquer)');
        $this->line('  php artisan budget:synchroniser-taches --appliquer --force');
        $this->line('Une ligne qui reste à 0 attend un collectif : la dotation de son compte est déjà prise.');

        return self::SUCCESS;
    }

    /** @return array{0: array, 1: array<int, string>} les lignes lues, puis les erreurs de fichier */
    protected function lireCsv(string $chemin): array
    {
        $h = fopen($chemin, 'r');
        $entete = fgetcsv($h, 0, ';');
        if ($entete === false) return [[], ['Fichier vide.']];

        $entete = array_map(fn($c) => trim((string) $c), $entete);
        $manquantes = array_diff(['id_tache', 'code_tache', 'code_nomenclature'], $entete);
        if ($manquantes !== []) {
            return [[], ['Colonnes obligatoires absentes de l\'en-tête : ' . implode(', ', $manquantes)]];
        }

        // $numero commence à 2 : l'en-tête occupe la ligne 1, donc le numéro cité dans un refus est
        // celui que l'agent voit dans sa feuille. Les lignes entièrement vides (Excel en laisse
        // après la dernière saisie, et les « ;; » du bout comptent comme vides) sont ignorées —
        // jamais un refus, qui bloquerait tout le fichier.
        $lignes = [];
        $numero = 1;
        while (($brute = fgetcsv($h, 0, ';')) !== false) {
            $numero++;
            $ligne = [];
            foreach ($entete as $i => $cle) $ligne[$cle] = trim((string) ($brute[$i] ?? ''));
            if (array_filter($ligne, fn($v) => $v !== '') === []) continue;
            $lignes[$numero] = $ligne;
        }
        fclose($h);

        return [$lignes, []];
    }

    /**
     * Le sort d'une ligne : ['pret', la ligne telle que la phase d'écriture la consomme],
     * ['deja_reliee', null] si le bon compte y est déjà, ['refus', la phrase]. Les modèles trouvés en
     * chemin sont rendus avec la ligne, pour ne pas les recharger à l'écriture.
     *
     * @param array<string, string> $ligne
     * @param \Illuminate\Support\Collection $comptesParCode
     * @param \Illuminate\Support\Collection<int, Service> $services
     */
    protected function controler(array $ligne, int $numero, Exercice $exercice, $comptesParCode, $services,
                                 bool $remplacer): array
    {
        $dit = "ligne $numero ({$ligne['code_tache']})";

        if (($ligne['code_nomenclature'] ?? '') === '') {
            return ['refus', "$dit — compte non renseigné."];
        }

        $tache = Tache::withoutGlobalScopes()->find((int) $ligne['id_tache']);
        if (!$tache) return ['refus', "$dit — tâche de id {$ligne['id_tache']} introuvable."];
        if ($tache->code !== $ligne['code_tache']) {
            return ['refus', "$dit — le code du fichier (« {$ligne['code_tache']} ») ne correspond plus à la base "
                . "(« {$tache->code} ») : le CSV est périmé."];
        }
        if ($tache->trashed()) return ['refus', "$dit — la tâche est supprimée."];
        if ((int) $tache->exercice_id !== (int) $exercice->id) {
            return ['refus', "$dit — la tâche est de l'exercice {$tache->exercice_id}, pas de {$exercice->annee}."];
        }
        if ($tache->niveau !== 'tache') {
            return ['refus', "$dit — c'est une sous-tâche : le compte se renseigne sur elle par sa tâche parente."];
        }
        if ($tache->sousTaches()->exists()) {
            return ['refus', "$dit — la tâche a des sous-tâches : le compte se renseigne sur chacune d'elles."];
        }

        $groupe = $comptesParCode->get($ligne['code_nomenclature'], collect());
        if ($groupe->isEmpty()) {
            return ['refus', "$dit — compte « {$ligne['code_nomenclature']} » inconnu, ou de dépense inactive."];
        }

        // Un même code vit souvent sur deux exercices (la contrainte d'unicité de la table est par
        // exercice) : un compte d'une autre année n'a aucune ligne au budget visé, et la tâche reliée
        // mentirait dans la matrice. Les comptes sans exercice sont admis, l'unicité ne les couvre pas.
        $retenu = $groupe->filter(fn($c) => $c->exercice_id === null
            || (int) $c->exercice_id === (int) $exercice->id);
        if ($retenu->count() === 0) {
            return ['refus', "$dit — compte « {$ligne['code_nomenclature']} » hors exercice : ce code n'est "
                . "actif qu'à une autre année, pas en {$exercice->annee}."];
        }
        if ($retenu->count() > 1) {
            return ['refus', "$dit — compte « {$ligne['code_nomenclature']} » ambigu (" . $retenu->count()
                . " comptes de dépense actifs portent ce code en {$exercice->annee})."];
        }
        $compte = $retenu->first();

        $dejaPose = $tache->nomenclature_id;
        if ($dejaPose !== null) {
            if ((int) $dejaPose === (int) $compte->id) return ['deja_reliee', null];
            if (!$remplacer) {
                $actuel = NomenclatureBudgetaire::withoutGlobalScopes()
                    ->whereNull('deleted_at')->find($dejaPose);
                return ['refus', "$dit — un compte est déjà posé (« " . ($actuel?->code ?? $dejaPose)
                    . " ») : relance avec --remplacer pour le changer."];
            }
        }

        $service = null;
        if (($ligne['service_code'] ?? '') !== '') {
            $service = $services->get($ligne['service_code']);
            if (!$service) return ['refus', "$dit — service « {$ligne['service_code']} » introuvable."];
        }

        return ['pret', [
            'numero'  => $numero,
            'tache'   => $tache,
            'compte'  => $compte,
            'service' => $service,
            'delai'   => ($ligne['delai'] ?? '') !== '' ? $ligne['delai'] : null,
            'guichet' => ($ligne['guichet'] ?? '') !== '' ? $ligne['guichet'] : null,
            'sp'      => $this->sousProgrammeDe($tache),
            // Un changement de compte ne touche ni le hors-crédit ni le nombre de lignes de la
            // matrice : la tâche y était déjà. C'est ce que séparent les deux promesses.
            'nouvelle' => $dejaPose === null,
        ]];
    }

    protected function sousProgrammeDe(Tache $tache): ?SousProgrammeEp
    {
        $programme = $tache->activite?->action?->programme;
        if (!$programme) return null;

        return SousProgrammeEp::withoutGlobalScopes()
            ->where('programme_budgetaire_id', $programme->id)
            ->orderBy('code')->first();
    }

    /** @param array<int, array> $prets @return array<int, string> les codes de SP touchés */
    protected function spConcernes(array $prets): array
    {
        return array_values(array_unique(array_filter(array_map(fn($p) => $p['sp']?->code, $prets))));
    }

    /** @return array<string, array> par code de sous-programme : hors-crédit, lignes, AE, CP */
    protected function instantanes(array $codes, Exercice $exercice): array
    {
        $etats = [];
        foreach (array_unique($codes) as $code) {
            $sp = SousProgrammeEp::withoutGlobalScopes()->where('code', $code)->first();
            if (!$sp) continue;
            $cadre = new CadreLogiqueEtendu($sp, $exercice);
            $etats[$code] = [
                'hors'   => count($cadre->anomalies()['sans_budget']),
                'lignes' => count($cadre->lignes()),
                'ae'     => round($cadre->totalAe(), 2),
                'cp'     => round($cadre->totalCp(), 2),
            ];
        }
        return $etats;
    }

    /**
     * Les trois promesses par sous-programme, et le détail compte par compte.
     *
     * @param array<int, array> $prets
     * @return array{table: string, promesses_manquees: array<int, string>, alertes: array<int, string>}
     */
    protected function verifier(array $avant, array $prets, Exercice $exercice): array
    {
        $parSp = [];
        foreach ($prets as $p) {
            $code = $p['sp']?->code ?? '(hors sous-programme)';
            $parSp[$code]['toutes']    = ($parSp[$code]['toutes'] ?? 0) + 1;
            $parSp[$code]['nouvelles'] = ($parSp[$code]['nouvelles'] ?? 0) + ($p['nouvelle'] ? 1 : 0);
        }

        $apres = $this->instantanes(array_keys($parSp), $exercice);

        $promesses = [];
        $table = "\n" . sprintf('%-9s %7s %9s %7s %7s %7s   %s',
            'SP', 'reliées', 'nouvelles', 'hors-', 'hors+', 'lignes ±', 'total AE (inchangé ?)');

        foreach ($parSp as $code => $n) {
            if (!isset($avant[$code], $apres[$code])) continue;
            $a = $avant[$code]; $b = $apres[$code];

            $table .= "\n" . sprintf('%-9s %7d %9d %7d %7d %7d   %s', $code, $n['toutes'], $n['nouvelles'],
                $a['hors'], $b['hors'], $b['lignes'] - $a['lignes'],
                $a['ae'] === $b['ae']
                    ? 'oui ' . number_format($b['ae'], 0, ',', ' ')
                    : 'NON ' . number_format($a['ae'], 0, ',', ' ') . ' → ' . number_format($b['ae'], 0, ',', ' '));

            if ($b['hors'] !== $a['hors'] - $n['nouvelles']) {
                $promesses[] = "$code : hors-crédit passé de $a[hors] à $b[hors], attendu "
                    . ($a['hors'] - $n['nouvelles']);
            }
            // L'étape 2a a changé cette donne : la matrice imprime la tâche saisie qu'un compte
            // lui soit lié ou non. Relier un compte ne fait donc plus pousser les lignes — cela
            // doit laisser la structure intacte — et le seul mouvement attendu est la baisse du
            // hors-crédit. Vérifier l'ancienne promesse faisait refuser l'import par lui-même.
            if ($b['lignes'] !== $a['lignes']) {
                $promesses[] = "$code : lignes passées de $a[lignes] à $b[lignes], attendu $a[lignes] — "
                    . 'relier un compte ne change plus la structure de la matrice';
            }
            if ($a['ae'] !== $b['ae'] || $a['cp'] !== $b['cp']) {
                $promesses[] = "$code : les totaux ont bougé alors qu'aucun montant n'était écrit";
            }
        }

        $malPosees = [];
        foreach ($prets as $p) {
            $actuel = (int) DB::table('taches')->where('id', $p['tache']->id)->value('nomenclature_id');
            if ($actuel !== (int) $p['compte']->id) $malPosees[] = $p['tache']->code;
        }
        if ($malPosees !== []) {
            $promesses[] = 'compte non posé sur : ' . implode(', ', $malPosees);
        }

        $alertes = [];
        foreach ($this->repartitions($prets, $exercice) as $cite) {
            $table .= "\n" . $cite['ligne'];
            if ($cite['recevra'] <= 0) $alertes[] = $cite['alerte'];
        }

        return ['table' => $table, 'promesses_manquees' => $promesses, 'alertes' => $alertes];
    }

    /**
     * Ce que `budget:synchroniser-taches` donnerait aux tâches reliées : l'écart du compte
     * (dotation actualisée − Σ CP des porteuses déjà en place) se répartit au prorata des CP,
     * donc une tâche à CP nul sur un compte déjà dosé ne recevra rien ; sur un compte dont toutes
     * les porteuses sont à 0, le partage se fait à parts égales.
     *
     * @param array<int, array> $prets
     * @return array<int, array{recevra: float, ligne: string, alerte: string}>
     */
    protected function repartitions(array $prets, Exercice $exercice): array
    {
        $budget = Budget::withoutGlobalScopes()
            ->where('exercice_id', $exercice->id)->where('statut', 'adopte')->first();

        $parCompte = [];
        foreach ($prets as $p) $parCompte[$p['compte']->id][] = $p;

        $lignes = $budget
            ? LigneBudgetaire::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('budget_id', $budget->id)
                ->whereIn('nomenclature_id', array_keys($parCompte))
                ->with(['nomenclature', 'budget'])->get()->keyBy('nomenclature_id')
            : collect();

        $rapport = [];
        foreach ($parCompte as $compteId => $groupes) {
            $compte   = $groupes[0]['compte'];
            $nouvelles = collect($groupes)->map(fn($g) => $g['tache']->id)->all();

            // get() et non $lignes[$compteId] : un compte sans ligne au budget n'a rien dans la
            // collection, et lire la clé absente ferait remonter un avertissement en exception.
            $ligne = $lignes->get($compteId);
            $dotation = round((float) ($ligne?->getBudgetRectifieReel() ?? 0), 2);

            // Les porteuses du compte, telles que la sync les verra : celles des lignes déjà en
            // place (hors nouvelles reliées), puis toutes, pour le diviseur du partage égal.
            $deja = Tache::withoutGlobalScopes()->porteusesBudget()->whereNull('taches.deleted_at')
                ->where('exercice_id', $exercice->id)->where('nomenclature_id', $compteId)
                ->whereNotIn('id', $nouvelles)->get();
            $cpDeja   = round((float) $deja->sum('cp'), 2);
            $porteuses = $deja->count() + count($nouvelles);
            $ecart    = round($dotation - $cpDeja, 2);

            // Le motif suit la règle du service : écart nul, ou prorata sur des CP déjà posés,
            // ou partage égal quand toutes les porteuses sont à zero.
            $recevra = 0.0;
            if ($ligne === null) {
                $motif = 'la sync ne donnera rien — aucune ligne à ce nom au budget de ' . $exercice->annee;
            } elseif ($dotation <= 0) {
                $motif = 'la sync ne donnera rien — aucun crédit ouvert à ce compte en ' . $exercice->annee;
            } elseif ($ecart <= 0) {
                $motif = 'la sync ne donnera rien — la dotation est déjà prise ('
                    . number_format($cpDeja, 0, ',', ' ') . ' FCFA de CP)';
            } elseif ($cpDeja > 0) {
                $motif = 'la sync ne donnera rien — le partage se fait au prorata des CP déjà posés';
            } else {
                $recevra = round($ecart / max(1, $porteuses), 2);
                $motif = 'la sync donnera ' . number_format($recevra, 0, ',', ' ') . ' FCFA';
            }

            foreach ($groupes as $p) {
                $rapport[] = [
                    'recevra' => $recevra,
                    'ligne'   => sprintf('   %-7s → %-9s dotation %15s écart %15s | %s',
                        $p['tache']->code, $compte->code, number_format($dotation, 0, ',', ' '),
                        number_format($ecart, 0, ',', ' '), $motif),
                    'alerte'  => $p['tache']->code . ' — compte ' . $compte->code . ' : ' . $motif,
                ];
            }
        }

        return $rapport;
    }
}
