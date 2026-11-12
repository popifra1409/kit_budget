<?php

namespace App\Services\Planification;

use App\Models\Action;
use App\Models\Activite;
use App\Models\Exercice;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use App\Models\Tache;
use Illuminate\Support\Collection;

/**
 * Cadre logique — modèle ÉTENDU : la matrice descend jusqu'à la tâche.
 *
 * Le CDMT (CadreLogiqueSousProgrammes) s'arrête à l'action et ne montre que des
 * indicateurs ; celui-ci part du même sous-programme EP mais creuse
 * Sous-programme -> Actions -> Activités -> Tâches, et aligne les huit colonnes de la
 * désignation au financement.
 *
 * Trois partis pris, à connaître avant de lire les totaux :
 *
 *  - UNE PORTEUSE SE RANGE SOUS L'ACTIVITÉ DE SA MÈRE. Une sous-tâche a un
 *    `activite_id` à elle, recopié à la saisie, qui peut contredire celui de sa mère :
 *    c'est le cas en base de douze sous-tâches de « T6 Charges diverses » déclarées dans
 *    l'activité « Avantages connexes ». L'agrégat du modèle, lui, suit la mère
 *    (Tache::getTotalAe() somme `sousTaches()`), donc la colonne de l'activité de
 *    l'écran de saisie. Ranger la ligne par son propre `activite_id` afficherait
 *    « Charges diverses : 0 » là où la saisie affiche 629 213 659 : l'état serait
 *    auto-cohérent et faux face au budget. `anomalies()` rend la liste de ces reclassements.
 *
 *  - `actif` ne filtre rien : Tache::recalculerBudget() et Action::getTotalAe() n'ignorent
 *    pas les lignes désactivées non plus. Filtrer ici désaccorderait l'état de la saisie.
 *
 *  - l'`exercice` est celui du constructeur, jamais celui de la session : le scope global
 *    `exercice` de HasExercice est neutralisé dans les eager-loads et remplacé par
 *    `exercice_id = ? OR NULL`, comme SousProgrammeEp::activitesPourExercice().
 *
 * Les lignes imprimées sont exactement l'ensemble `Tache::porteusesBudget()` : une tâche
 * qui a des sous-tâches est représentée par ses filles (les deux compteraient double),
 * une tâche sans fille n'est imprimée que si elle porte une nomenclature. La somme des
 * lignes d'une activité égale donc Activite::getTotalAe(), sans double comptage.
 *
 * « Indicateur de résultat » est la colonne texte de la tâche : les Indicateurs
 * structurés (table `indicateurs`, polymorphe action/activité) appartiennent au CDMT et
 * ne sont pas relus ici.
 */
class CadreLogiqueEtendu
{
    /**
     * Les colonnes d'impression : les huit demandées, plus la désignation qui porte la
     * paire Tâche / Sous-tâche. Source unique du PDF et de l'Excel.
     */
    public const COLONNES = [
        'designation'    => 'Tâche / Sous-tâche',
        'nomenclature'   => 'Code de nomenclature',
        'ae'             => 'AE (FCFA)',
        'cp'             => 'CP (FCFA)',
        'delai'          => 'Délai de réalisation',
        'guichet'        => 'Guichet',
        'service'        => 'Service responsable',
        'indicateur'     => 'Indicateur de résultat',
        'resultat'       => 'Résultat attendu',
    ];

    /** @var Collection<int, Action>|null une seule descente en base par instance */
    private ?Collection $actions = null;

    public function __construct(
        public readonly SousProgrammeEp $sousProgramme,
        public readonly Exercice $exercice,
    ) {}

    /**
     * Actions du sous-programme pour l'exercice, avec la chaîne déjà chargée : c'est la
     * seule requête du service, les totaux s'additionnent en mémoire.
     *
     * @return Collection<int, Action>
     */
    public function actions(): Collection
    {
        if ($this->actions !== null) {
            return $this->actions;
        }

        $exerciceId = $this->exercice->id;

        return $this->actions = $this->sousProgramme->actionsPourExercice($exerciceId)
            // Programme et NomenclatureBudgetaire portent le scope global `exercice` :
            // sans neutralisation, un état d'exercice clos rendu sous une session d'un
            // autre exercice perd son programme porteur, son pilote et ses codes de
            // nomenclature — la ligne reste affichée, les cellules se vident.
            ->with([
                'programme' => fn($q) => $q->withoutGlobalScope('exercice')->with('responsablePersonnel'),
                'objectifsSpecifiques',
                'responsable',
                'activites' => fn($q) => $q->withoutGlobalScope('exercice')
                    ->where(fn($w) => $w->where('exercice_id', $exerciceId)->orWhereNull('exercice_id'))
                    ->orderBy('ordre'),
                // Les tâches principales de l'activité, puis leurs filles porteuses :
                // c'est par la mère que la ligne se range (voir l'en-tête de classe).
                'activites.taches' => fn($q) => $q->withoutGlobalScope('exercice')
                    ->where('niveau', 'tache')
                    ->orderBy('ordre')->orderBy('id')
                    ->with([
                        'nomenclature' => fn($n) => $n->withoutGlobalScope('exercice'),
                        'service',
                        'sousTaches' => fn($s) => $s->withoutGlobalScope('exercice')
                            ->where('niveau', 'sous_tache')
                            ->orderBy('ordre')->orderBy('id')
                            ->with([
                                'nomenclature' => fn($n) => $n->withoutGlobalScope('exercice'),
                                'service',
                            ]),
                    ]),
            ])
            ->get();
    }

    /**
     * La matrice : un bloc par action, un sous-bloc par activité, une ligne par tâche
     * porteuse. Une activité sans tâche et une action sans activité gardent leur bloc :
     * un état qui ampute la structure rend la colonne des totaux fausse, et le lecteur
     * ne peut plus distinguer « rien à programmer » de « rien ici ».
     *
     * @return array<int, array{
     *     action: Action, titre: string, objectif: ?string, responsable: ?string,
     *     programme: ?string, pilote: ?string, ae: float, cp: float,
     *     activites: array<int, array{activite: Activite, code: ?string, titre: ?string,
     *         responsable: ?string, ae: float, cp: float, lignes: array<int, array>}>
     * }>
     */
    public function blocs(): array
    {
        return $this->actions()
            ->map(function (Action $action) {
                $activites = $action->activites->map(fn(Activite $activite) => $this->sousBloc($activite))->all();

                return [
                    'action'      => $action,
                    'titre'       => trim("Action {$action->code} : {$action->libelle}"),
                    'objectif'    => $action->objectifPourCadreLogique(),
                    'responsable' => $action->responsable?->name,
                    'programme'   => $action->programme
                        ? trim("{$action->programme->code} : {$action->programme->libelle}") : null,
                    // L'agent désigné sur le Programme porteur : le même que la fiche
                    // « Voir » du programme (tâche #43), aux deux endroits du SIGB.
                    'pilote'      => $action->programme?->responsablePersonnel?->nom_complet,
                    'ae'          => array_sum(array_column($activites, 'ae')),
                    'cp'          => array_sum(array_column($activites, 'cp')),
                    'activites'   => $activites,
                ];
            })
            ->all();
    }

    /** @return array<int, array{activite: Activite, code: ?string, titre: ?string, responsable: ?string, ae: float, cp: float, lignes: array<int, array>}> */
    protected function sousBloc(Activite $activite): array
    {
        $lignes = [];

        foreach ($activite->taches as $mere) {
            if ($mere->sousTaches->isNotEmpty()) {
                // La mère n'est pas une ligne : son ae est déjà la somme de ses filles.
                foreach ($mere->sousTaches as $fille) {
                    $lignes[] = $this->ligne($fille, $mere);
                }
                continue;
            }

            if ($mere->estPorteuseBudget()) {
                $lignes[] = $this->ligne($mere, null);
            }
            // Ni fille ni nomenclature : la tâche ne finance rien, elle n'a pas de case
            // dans une matrice de crédits. Elle resterait une ligne à 0 FCFA.
        }

        return [
            'activite'    => $activite,
            'code'        => $activite->code,
            'titre'       => $activite->libelle,
            'responsable' => $activite->responsable?->name,
            'ae'          => array_sum(array_column($lignes, 'ae')),
            'cp'          => array_sum(array_column($lignes, 'cp')),
            'lignes'      => $lignes,
        ];
    }

    /**
     * Une ligne = les huit colonnes d'une tâche porteuse.
     *
     * @param  Tache      $tache la porteuse imprimée
     * @param  ?Tache     $mere  sa mère quand la porteuse est une sous-tâche
     * @return array{designation: string[], code: ?string, nomenclature: ?string,
     *     ae: float, cp: float, delai: ?string, guichet: ?string, service: ?string,
     *     indicateur: ?string, resultat: ?string, tache: Tache, mere: ?Tache}
     */
    protected function ligne(Tache $tache, ?Tache $mere): array
    {
        // Une seule colonne « Tâche / Sous-tâche » : la mère n'a pas de nomenclature, donc
        // pas de case budgétaire ; deux colonnes auraient dédoublé la hiérarchie.
        $designation = [];
        if ($mere) {
            $designation[] = trim("{$mere->code} — {$mere->libelle}");
        }
        $designation[] = trim("{$tache->code} — {$tache->libelle}");

        return [
            'designation'  => $designation,
            'code'         => $tache->code,
            'nomenclature' => $tache->nomenclature?->code,
            'ae'           => (float) $tache->ae,
            'cp'           => (float) $tache->cp,
            'delai'        => filled($tache->delai) ? $tache->delai : null,
            'guichet'      => filled($tache->guichet) ? $tache->guichet : null,
            'service'      => $tache->service?->nom,
            'indicateur'   => filled($tache->indicateur_resultat) ? $tache->indicateur_resultat : null,
            'resultat'     => filled($tache->resultat_attendu) ? $tache->resultat_attendu : null,
            'tache'        => $tache,
            'mere'         => $mere,
        ];
    }

    /** @return array<int, array> Une ligne plate par tâche porteuse, contexte inclus — pour Excel. */
    public function lignes(): array
    {
        $plates = [];

        foreach ($this->blocs() as $bloc) {
            foreach ($bloc['activites'] as $sousBloc) {
                foreach ($sousBloc['lignes'] as $ligne) {
                    $plates[] = $ligne + [
                        'action'        => $bloc['action'],
                        'action_code'   => $bloc['action']->code,
                        'activite'      => $sousBloc['activite'],
                        'activite_code' => $sousBloc['code'],
                    ];
                }
            }
        }

        return $plates;
    }

    /**
     * Les porteuses que la descente par la mère ne pouvait pas trouver : celles dont la
     * mère vit hors du sous-programme (elles s'impriment dans un autre état), et celles
     * que l'état imprime alors que leur propre `activite_id` les donne à un autre
     * sous-programme. Une liste vide dit que les deux vues de la base concordent.
     *
     * @return array{reclassees: array<int, array>, absentes: array<int, array>}
     */
    public function anomalies(): array
    {
        $imprimees = collect($this->lignes())->map(fn($l) => $l['tache']->id);

        // La vue « propre activite_id », telle que la donnent les écrans de liste.
        $parActivite = Tache::withoutGlobalScopes()->porteusesBudget()
            ->whereIn('activite_id', $this->activites()->keys())
            ->with('activite.action')
            ->get();

        $absentes = $parActivite->whereNotIn('id', $imprimees->all())->map(fn(Tache $t) => [
            'tache'        => $t->id,
            'code'         => $t->code,
            'libelle'      => $t->libelle,
            'activite_id'  => $t->activite_id,
            'mere_id'      => $t->parent_id,
            'ae'           => (float) $t->ae,
        ])->values()->all();

        $reclassees = collect($this->lignes())
            ->filter(fn($l) => $l['mere'] !== null && (int) $l['tache']->activite_id !== (int) $l['mere']->activite_id)
            ->map(fn($l) => [
                'tache'        => $l['tache']->id,
                'code'         => $l['tache']->code,
                'libelle'      => $l['tache']->libelle,
                'activite_incrite' => $l['tache']->activite_id,
                'activite_imprimee' => $l['mere']->activite_id,
                'mere_code'    => $l['mere']->code,
                'ae'           => $l['ae'],
            ])->values()->all();

        return ['reclassees' => $reclassees, 'absentes' => $absentes];
    }

    /** @return Collection<int, Activite> les activités du sous-programme pour l'exercice */
    public function activites(): Collection
    {
        return $this->actions()->flatMap(fn(Action $action) => $action->activites)->keyBy('id');
    }

    public function totalAe(): float
    {
        return array_sum(array_column($this->blocs(), 'ae'));
    }

    public function totalCp(): float
    {
        return array_sum(array_column($this->blocs(), 'cp'));
    }

    public function estVide(): bool
    {
        return $this->actions()->isEmpty();
    }

    /** Le sous-programme tel que l'imprime l'en-tête de l'état. */
    public function titre(): string
    {
        return trim("{$this->sousProgramme->code} : {$this->sousProgramme->libelle}");
    }

    /**
     * Le pilotage : un couple programme -> agent responsable par programme porteur, pour
     * l'en-tête. Un programme sans agent désigné remonte quand même, sinon l'état laisse
     * croire que la case est facultative.
     *
     * @return array<int, array{programme: string, pilote: ?string}>
     */
    public function pilotage(): array
    {
        $vus = [];

        foreach ($this->actions() as $action) {
            $programme = $action->programme;
            if (!$programme instanceof Programme || isset($vus[$programme->id])) {
                continue;
            }

            $vus[$programme->id] = [
                'programme' => trim("{$programme->code} : {$programme->libelle}"),
                'pilote'    => $programme->responsablePersonnel?->nom_complet,
            ];
        }

        return array_values($vus);
    }

    public static function formatMontant(float $montant): string
    {
        return number_format($montant, 0, ',', ' ').' FCFA';
    }
}
