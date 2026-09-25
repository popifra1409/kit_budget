<?php

namespace App\Services\SuiviEvaluation;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\PlanStrategiqueEp;
use App\Models\SousProgrammeEp;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MatriceArrimageService
{
    protected const GRAVITES = ['critique' => 1, 'majeure' => 2, 'mineure' => 3];

    /** Structure d'une ligne du tableau (une ligne = une sous-tache ou un message). */
    protected const VIDE = [
        'action' => null,
        'activite' => null,
        'tache' => null,
        'ligne' => null,
        'message' => null,
        'message_colspan' => 0,
        'ctx' => [],
    ];

    // Etat de travail, reinitialise pour chaque sous-programme
    protected array $anomalies = [];
    protected int $controlesOk = 0;
    protected int $controlesTotal = 0;
    protected array $tauxIndicateurs = [];
    protected array $tauxExtrants = [];
    protected float $totalAe = 0;
    protected float $totalCp = 0;
    protected float $totalEngage = 0;
    protected array $compteurs = [];
    protected string $annee = '';

    public function __construct(protected ExecutionBudgetaireService $execution) {}

    /**
     * Sous-programmes que l'utilisateur a le droit de voir : tous, ou
     * uniquement ceux dont il est responsable. Filtre applique cote serveur.
     */
    public function sousProgrammesVisibles(PlanStrategiqueEp $psp, User $user): Builder
    {
        return SousProgrammeEp::query()
            ->where('plan_strategique_ep_id', $psp->id)
            ->when(!$user->can('view_all_matrice_arrimage'), fn($q) => $q->where('responsable_id', $user->id))
            ->orderBy('code');
    }

    public function construire(PlanStrategiqueEp $psp, int $exerciceId, User $user, ?int $sousProgrammeId = null): array
    {
        $psp->loadMissing('cspMinistere');
        $exercice = Exercice::find($exerciceId);
        $this->annee = (string) $exercice?->annee;

        $blocs = $this->sousProgrammesVisibles($psp, $user)
            ->when($sousProgrammeId, fn($q) => $q->whereKey($sousProgrammeId))
            ->with(['programmeBudgetaire', 'responsable', 'indicateurs.valeurs'])
            ->get()
            ->map(fn(SousProgrammeEp $sp) => $this->construireSousProgramme($sp, $exerciceId));

        $cpGlobal = $blocs->sum('cp');
        $engageGlobal = $blocs->sum('engage');

        $blocs = $blocs->map(fn($b) => $b + [
            'part_budget' => $cpGlobal > 0 ? round($b['cp'] / $cpGlobal * 100, 1) : 0,
        ]);

        return [
            'psp'             => $psp,
            'csp'             => $psp->cspMinistere,
            'exercice'        => $exercice,
            'sous_programmes' => $blocs,
            'totaux'          => [
                'ae'                  => $blocs->sum('ae'),
                'cp'                  => $cpGlobal,
                'engage'              => $engageGlobal,
                'taux_execution'      => $cpGlobal > 0 ? round($engageGlobal / $cpGlobal * 100, 1) : 0,
                'completude'          => $blocs->isNotEmpty() ? round($blocs->avg('score_completude'), 1) : 0,
                'anomalies_critiques' => $blocs->sum(fn($b) => $b['anomalies']->where('gravite', 'critique')->count()),
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Sous-programme
    // ────────────────────────────────────────────────────────────────
    protected function construireSousProgramme(SousProgrammeEp $sp, int $exerciceId): array
    {
        $this->reinitialiser();
        $actions = $sp->actionsPourExercice($exerciceId)->get();

        $this->verifier(filled($sp->objectif), 'majeure', 'Sous-programme sans objectif formulé');
        $this->verifier((bool) $sp->responsable_id, 'majeure', 'Sous-programme sans responsable désigné');
        $this->verifier((bool) $sp->programme_budgetaire_id, 'critique', 'Sous-programme non rattaché à un programme budgétaire');
        $this->verifier(filled($sp->code_programme_ep), 'critique', "Aucun programme budgétaire de l'EP rattaché (actions introuvables)");
        $this->verifier($sp->indicateurs->isNotEmpty(), 'majeure', 'Aucun indicateur au niveau du sous-programme');
        $this->verifier($actions->isNotEmpty(), 'critique', 'Aucune action rattachée au sous-programme');

        $activitesParAction = Activite::withoutGlobalScope('exercice')
            ->whereIn('action_id', $actions->pluck('id'))
            ->where('exercice_id', $exerciceId)
            ->with(['responsable', 'extrants', 'indicateurs.valeurs', 'taches' => fn($q) => $q->withoutGlobalScope('exercice')])
            ->orderBy('code')
            ->get()
            ->groupBy('action_id');

        $lignes = [];

        foreach ($actions as $action) {
            $this->compteurs['actions']++;
            $lignesAction = [];

            foreach ($activitesParAction->get($action->id, collect()) as $activite) {
                array_push($lignesAction, ...$this->lignesActivite($activite, $exerciceId, $action->libelle));
            }

            $aDesActivites = !empty($lignesAction);
            $this->verifier($aDesActivites, 'majeure', "Action « {$action->libelle} » : aucune activité programmée sur l'exercice {$this->annee}");

            if (!$aDesActivites) {
                $lignesAction[] = $this->ligneVide(['action' => $action->libelle], "Aucune activité programmée sur l'exercice", 10);
            }

            $lignesAction[0]['action'] = [
                'code' => $action->code,
                'libelle' => $action->libelle,
                'rowspan' => count($lignesAction),
            ];

            array_push($lignes, ...$lignesAction);
        }

        // Indicateurs du SP (formates en dernier : alimentent aussi le score de performance)
        $indicateursSp = $sp->indicateurs->map(fn($i) => $this->formaterIndicateur($i))->all();

        $completude  = $this->controlesTotal > 0 ? round($this->controlesOk / $this->controlesTotal * 100, 1) : 0;
        $performance = $this->moyenne($this->tauxIndicateurs);

        return [
            'sp'                => $sp,
            'indicateurs'       => $indicateursSp,
            'lignes'            => $lignes,
            'compteurs'         => $this->compteurs,
            'ae'                => $this->totalAe,
            'cp'                => $this->totalCp,
            'engage'            => $this->totalEngage,
            'taux_execution'    => $this->totalCp > 0 ? round($this->totalEngage / $this->totalCp * 100, 1) : 0,
            'score_completude'  => $completude,
            'score_performance' => $performance,
            'score_extrants'    => $this->moyenne($this->tauxExtrants),
            'appreciation'      => $this->apprecier($completude, $performance),
            'anomalies'         => collect($this->anomalies)
                ->sortBy(fn($a) => self::GRAVITES[$a['gravite']])->values(),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Activite -> Taches -> Sous-taches (lignes du tableau)
    // ────────────────────────────────────────────────────────────────
    protected function lignesActivite(Activite $activite, int $exerciceId, string $actionLibelle): array
    {
        $this->compteurs['activites']++;
        $nom = "Activité « {$activite->libelle} »";

        $this->verifier($activite->indicateurs->isNotEmpty(), 'majeure', "{$nom} : aucun indicateur");
        $this->verifier($activite->indicateurs->every(fn($i) => filled($i->valeur_cible)), 'majeure', "{$nom} : indicateur(s) sans valeur cible");
        $this->verifier($activite->extrants->isNotEmpty(), 'mineure', "{$nom} : aucun extrant défini");
        $this->verifier((bool) $activite->responsable_id, 'mineure', "{$nom} : sans responsable de mise en œuvre");
        $this->verifier($activite->taches->isNotEmpty(), 'critique', "{$nom} : aucune tâche (activité non budgétisée)");

        $indicateurs = $activite->indicateurs->map(fn($i) => $this->formaterIndicateur($i))->all();

        $extrants = $activite->extrants->map(function ($e) {
            $taux = $e->getTauxRealisation();
            if ($taux !== null) {
                $this->tauxExtrants[] = min($taux, 100);
            }
            return [
                'libelle' => $e->libelle,
                'prevu' => $e->quantite_prevue,
                'realise' => $e->quantite_realisee,
                'unite' => $e->unite_mesure,
                'statut' => $e->statut,
                'taux' => $taux,
            ];
        })->all();

        $this->compteurs['extrants'] += count($extrants);
        $this->compteurs['indicateurs'] += count($indicateurs);

        // Contexte "a plat" pour l'export Excel (une ligne = toutes ses colonnes)
        $ctx = [
            'action'               => $actionLibelle,
            'activite'             => $activite->libelle,
            'responsable_activite' => $activite->responsable?->name,
            'extrants'             => collect($extrants)->pluck('libelle')->implode(' | '),
            'indicateurs'          => collect($indicateurs)
                ->map(fn($i) => "{$i['libelle']} (cible " . ($i['cible'] ?? '—') . ', réalisé ' . ($i['realise'] ?? '—') . ')')
                ->implode(' | '),
        ];

        $parents    = $activite->taches->where('niveau', 'tache');
        $sousTaches = $activite->taches->where('niveau', 'sous_tache');

        $groupes = $parents->map(fn($t) => [$t, $sousTaches->where('parent_id', $t->id)])->values();

        $orphelines = $sousTaches->whereNotIn('parent_id', $parents->pluck('id'));
        if ($orphelines->isNotEmpty()) {
            $groupes->push([null, $orphelines]);
        }

        $lignes = [];

        foreach ($groupes as [$tache, $enfants]) {
            if ($tache) {
                $this->compteurs['taches']++;
            }

            // Tache sans sous-tache : elle porte elle-meme l'imputation budgetaire
            $feuilles = $enfants->isNotEmpty() ? $enfants : collect([$tache]);

            $lignesTache = $feuilles->map(fn(Tache $st) => [
                'ligne' => $this->ligneBudgetaire($st, $exerciceId),
                'ctx'   => $ctx + ['tache' => $tache?->libelle],
            ] + self::VIDE)->values()->all();

            $lignesTache[0]['tache'] = [
                'libelle' => $tache?->libelle ?? '(sous-tâches sans tâche parente)',
                'rowspan' => count($lignesTache),
            ];

            array_push($lignes, ...$lignesTache);
        }

        if (empty($lignes)) {
            $lignes[] = $this->ligneVide($ctx, 'Aucune tâche : activité non budgétisée', 7);
        }

        $lignes[0]['activite'] = [
            'code'        => $activite->code,
            'libelle'     => $activite->libelle,
            'objectif'    => $activite->objectif,
            'zone'        => $activite->zone_execution,
            'responsable' => $activite->responsable?->name,
            'indicateurs' => $indicateurs,
            'extrants'    => $extrants,
            'rowspan'     => count($lignes),
        ];

        return $lignes;
    }

    protected function ligneBudgetaire(Tache $st, int $exerciceId): array
    {
        $this->compteurs['lignes']++;

        $lb = $st->ligneBudgetaire();
        $this->verifier((bool) $lb, 'critique', "« {$st->libelle} » : aucune ligne budgétaire correspondante");

        // Prorata des AE si la ligne est partagee entre plusieurs sous-taches (evite le double comptage)
        $part   = $lb ? $this->execution->quotePart($st, $exerciceId) : 0.0;
        $ae     = (float) $st->ae;
        $cp     = (float) $st->cp;
        $engage = $lb ? round((float) $lb->engage * $part, 2) : 0.0;
        $taux   = $cp > 0 ? round($engage / $cp * 100, 1) : null;

        if ($taux !== null && $taux > 100) {
            $this->signaler('majeure', "« {$st->libelle} » : engagé supérieur aux CP prévus ({$taux} %)");
        }

        $this->totalAe += $ae;
        $this->totalCp += $cp;
        $this->totalEngage += $engage;

        return [
            'sous_tache'   => $st->libelle,
            'code'         => $lb?->nomenclature?->code,
            'nomenclature' => $lb?->nomenclature?->libelle,
            'ae'           => $ae,
            'cp'           => $cp,
            'engage'       => $engage,
            'quote_part'   => $part,
            'taux'         => $taux,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────
    protected function formaterIndicateur($indicateur): array
    {
        $valeur = $indicateur->valeurs
            ->filter(fn($v) => str_starts_with((string) $v->periode, $this->annee))
            ->sortByDesc('periode')
            ->first();

        $cible   = is_numeric($indicateur->valeur_cible) ? (float) $indicateur->valeur_cible : null;
        $realise = is_numeric($valeur?->valeur_realisee) ? (float) $valeur->valeur_realisee : null;
        $taux    = ($cible && $realise !== null) ? round($realise / $cible * 100, 1) : null;

        if ($taux !== null) {
            $this->tauxIndicateurs[] = min($taux, 100); // plafonne : un depassement ne masque pas un retard ailleurs
        }

        return [
            'libelle'   => $indicateur->libelle,
            'unite'     => $indicateur->unite_mesure,
            'reference' => $indicateur->valeur_reference,
            'cible'     => $indicateur->valeur_cible,
            'realise'   => $valeur?->valeur_realisee,
            'periode'   => $valeur?->periode,
            'taux'      => $taux,
        ];
    }

    protected function apprecier(float $completude, ?float $performance): array
    {
        $s = config('suivi_evaluation.matrice');

        if ($completude >= $s['aligne_completude'] && ($performance === null || $performance >= $s['aligne_performance'])) {
            return [
                'libelle' => $performance === null ? 'Aligné (performance non mesurée)' : 'Aligné',
                'couleur' => 'success',
            ];
        }

        if ($completude >= $s['partiel_completude']) {
            return ['libelle' => 'Partiellement aligné', 'couleur' => 'warning'];
        }

        return ['libelle' => 'À renforcer', 'couleur' => 'danger'];
    }

    protected function verifier(bool $conforme, string $gravite, string $message): void
    {
        $this->controlesTotal++;
        $conforme ? $this->controlesOk++ : $this->signaler($gravite, $message);
    }

    protected function signaler(string $gravite, string $message): void
    {
        $this->anomalies[] = ['gravite' => $gravite, 'message' => $message];
    }

    protected function ligneVide(array $ctx, string $message, int $colspan): array
    {
        return ['ctx' => $ctx, 'message' => $message, 'message_colspan' => $colspan] + self::VIDE;
    }

    protected function moyenne(array $valeurs): ?float
    {
        return empty($valeurs) ? null : round(array_sum($valeurs) / count($valeurs), 1);
    }

    protected function reinitialiser(): void
    {
        $this->anomalies = [];
        $this->controlesOk = $this->controlesTotal = 0;
        $this->tauxIndicateurs = $this->tauxExtrants = [];
        $this->totalAe = $this->totalCp = $this->totalEngage = 0;
        $this->compteurs = ['actions' => 0, 'activites' => 0, 'taches' => 0, 'lignes' => 0, 'extrants' => 0, 'indicateurs' => 0];
    }
}
