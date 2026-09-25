<?php

namespace App\Services\Planification;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\NomenclatureBudgetaire;
use App\Models\PlanStrategiqueEp;
use App\Models\SousProgrammeEp;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ArborescenceLibellesService
{
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
    protected array $observations = [];
    protected array $vus = [];
    protected array $compteurs = [];
    protected Collection $nomenclatures;

    /** Tous les SP, ou seulement ceux dont l'utilisateur est responsable (filtre cote serveur). */
    public function sousProgrammesVisibles(PlanStrategiqueEp $psp, User $user): Builder
    {
        return SousProgrammeEp::query()
            ->where('plan_strategique_ep_id', $psp->id)
            ->when(!$user->can('view_all_arborescence_libelles'), fn($q) => $q->where('responsable_id', $user->id))
            ->orderBy('code');
    }

    public function construire(PlanStrategiqueEp $psp, int $exerciceId, User $user, ?int $sousProgrammeId = null): array
    {
        $psp->loadMissing('cspMinistere');

        $sps = $this->sousProgrammesVisibles($psp, $user)
            ->when($sousProgrammeId, fn($q) => $q->whereKey($sousProgrammeId))
            ->with(['programmeBudgetaire', 'responsable', 'indicateurs', 'actions'])
            ->get();

        $activites = Activite::query()
            ->whereIn('action_id', $sps->flatMap->actions->pluck('id'))
            ->where('exercice_id', $exerciceId)
            ->with(['extrants', 'indicateurs', 'taches'])
            ->orderBy('code')
            ->get();

        // Toutes les lignes de nomenclature en une seule requete
        $this->nomenclatures = NomenclatureBudgetaire::query()
            ->whereIn('id', $activites->flatMap->taches->pluck('nomenclature_id')->filter()->unique())
            ->get(['id', 'code', 'libelle'])
            ->keyBy('id');

        $parAction = $activites->groupBy('action_id');

        return [
            'psp'             => $psp,
            'csp'             => $psp->cspMinistere,
            'exercice'        => Exercice::find($exerciceId),
            'sous_programmes' => $sps->map(fn(SousProgrammeEp $sp) => $this->construireSousProgramme($sp, $parAction)),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    protected function construireSousProgramme(SousProgrammeEp $sp, Collection $parAction): array
    {
        $this->observations = [];
        $this->vus = [];
        $this->compteurs = array_fill_keys(['actions', 'activites', 'extrants', 'indicateurs', 'taches', 'sous_taches'], 0);

        $alertesSp = $this->controler('Sous-programme', $sp->libelle);

        if (blank($sp->objectif)) {
            $this->observer('Sous-programme', $sp->libelle, 'Objectif du sous-programme non formulé');
        }
        if (!$sp->programmeBudgetaire) {
            $this->observer('Sous-programme', $sp->libelle, 'Aucun programme de rattachement');
        }

        $indicateursSp = $sp->indicateurs->map(fn($i) => $this->indicateur($i))->all();
        if (empty($indicateursSp)) {
            $this->observer('Sous-programme', $sp->libelle, 'Aucun indicateur de sous-programme');
        }
        if ($sp->actions->isEmpty()) {
            $this->observer('Sous-programme', $sp->libelle, 'Aucune action rattachée');
        }

        $lignes = [];

        foreach ($sp->actions->sortBy('code') as $action) {
            $this->compteurs['actions']++;
            $texteAction = $this->codeLibelle($action->code, $action->libelle);
            $alertes = $this->controler('Action', $action->libelle, $sp->libelle, doublon: true);

            $lignesAction = [];
            foreach ($parAction->get($action->id, collect()) as $activite) {
                array_push($lignesAction, ...$this->lignesActivite($activite, $texteAction, $action->libelle));
            }

            if (empty($lignesAction)) {
                $this->observer('Action', $action->libelle, "Aucune activité sur l'exercice");
                $lignesAction[] = $this->ligneVide(['action' => $texteAction], "Aucune activité sur l'exercice", 6);
            }

            $lignesAction[0]['action'] = ['texte' => $texteAction, 'alertes' => $alertes, 'rowspan' => count($lignesAction)];
            array_push($lignes, ...$lignesAction);
        }

        return [
            'sp'           => $sp,
            'sp_texte'     => $this->codeLibelle($sp->code, $sp->libelle),
            'sp_alertes'   => $alertesSp,
            'programme'    => $sp->programmeBudgetaire
                ? $this->codeLibelle($sp->programmeBudgetaire->code, $sp->programmeBudgetaire->libelle)
                : null,
            'indicateurs'  => $indicateursSp,
            'lignes'       => $lignes,
            'compteurs'    => $this->compteurs,
            'observations' => collect($this->observations),
        ];
    }

    protected function lignesActivite(Activite $activite, string $texteAction, string $libelleAction): array
    {
        $this->compteurs['activites']++;
        $texteActivite = $this->codeLibelle($activite->code, $activite->libelle);
        $alertes = $this->controler('Activité', $activite->libelle, $libelleAction, doublon: true);

        $extrants = $activite->extrants->map(function ($e) {
            $this->compteurs['extrants']++;
            return [
                'texte'   => $e->libelle . ($e->unite_mesure ? " ({$e->unite_mesure})" : ''),
                'alertes' => $this->controler('Extrant', $e->libelle),
            ];
        })->all();

        if (empty($extrants)) {
            $this->observer('Activité', $activite->libelle, 'Aucun extrant défini');
        }

        $indicateurs = $activite->indicateurs->map(fn($i) => $this->indicateur($i))->all();
        if (empty($indicateurs)) {
            $this->observer('Activité', $activite->libelle, 'Aucun indicateur');
        }

        // Contexte "a plat" pour l'export Excel
        $ctx = [
            'action'      => $texteAction,
            'activite'    => $texteActivite,
            'extrants'    => collect($extrants)->pluck('texte')->implode(' | '),
            'indicateurs' => collect($indicateurs)->pluck('texte')->implode(' | '),
        ];

        $parents    = $activite->taches->where('niveau', 'tache')->sortBy('code');
        $sousTaches = $activite->taches->where('niveau', 'sous_tache');

        $groupes = $parents->map(fn($t) => [$t, $sousTaches->where('parent_id', $t->id)->sortBy('code')])->values();

        $orphelines = $sousTaches->whereNotIn('parent_id', $parents->pluck('id'));
        if ($orphelines->isNotEmpty()) {
            $groupes->push([null, $orphelines]);
        }

        $lignes = [];

        foreach ($groupes as [$tache, $enfants]) {
            $texteTache = $tache ? $this->codeLibelle($tache->code, $tache->libelle) : '(sous-tâches sans tâche parente)';
            $alertesTache = [];

            if ($tache) {
                $this->compteurs['taches']++;
                $alertesTache = $this->controler('Tâche', $tache->libelle, $activite->libelle);
            }

            // Tache sans sous-tache : elle porte elle-meme l'imputation
            $feuilles = $enfants->isNotEmpty() ? $enfants : collect([$tache]);

            $lignesTache = $feuilles->map(fn(Tache $st) => [
                'ligne' => $this->ligneSousTache($st, $tache),
                'ctx'   => $ctx + ['tache' => $texteTache],
            ] + self::VIDE)->values()->all();

            $lignesTache[0]['tache'] = ['texte' => $texteTache, 'alertes' => $alertesTache, 'rowspan' => count($lignesTache)];
            array_push($lignes, ...$lignesTache);
        }

        if (empty($lignes)) {
            $this->observer('Activité', $activite->libelle, 'Aucune tâche définie');
            $lignes[] = $this->ligneVide($ctx, 'Aucune tâche définie', 3);
        }

        $lignes[0]['activite'] = [
            'texte'       => $texteActivite,
            'alertes'     => $alertes,
            'extrants'    => $extrants,
            'indicateurs' => $indicateurs,
            'rowspan'     => count($lignes),
        ];

        return $lignes;
    }

    protected function ligneSousTache(Tache $st, ?Tache $tache): array
    {
        $estLaTache = $tache && $st->is($tache);
        if (!$estLaTache) {
            $this->compteurs['sous_taches']++;
        }

        $nomenclature = $this->nomenclatures->get($st->nomenclature_id);
        if (!$nomenclature) {
            $this->observer($estLaTache ? 'Tâche' : 'Sous-tâche', $st->libelle, 'Aucune ligne de nomenclature budgétaire');
        }

        return [
            'sous_tache'   => $estLaTache ? null : $this->codeLibelle($st->code, $st->libelle),
            'nomenclature' => $nomenclature ? "{$nomenclature->code} - {$nomenclature->libelle}" : null,
        ];
    }

    protected function indicateur($indicateur): array
    {
        $this->compteurs['indicateurs']++;
        $alertes = $this->controler('Indicateur', $indicateur->libelle);

        if (blank($indicateur->unite_mesure)) {
            $alertes[] = 'Unité de mesure non précisée';
            $this->observer('Indicateur', $indicateur->libelle, 'Unité de mesure non précisée');
        }

        return [
            'texte'   => $indicateur->libelle . ($indicateur->unite_mesure ? " ({$indicateur->unite_mesure})" : ''),
            'alertes' => $alertes,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Controles de formulation
    // ────────────────────────────────────────────────────────────────
    protected function controler(string $niveau, ?string $libelle, ?string $parent = null, bool $doublon = false): array
    {
        $cfg = config('planification.libelles');
        $libelle = trim((string) $libelle);
        $constats = [];

        if ($libelle === '') {
            $constats[] = 'Libellé vide';
        } else {
            $norm = $this->normaliser($libelle);
            $minMots = $cfg['min_mots'][$niveau] ?? null;

            if ($minMots && str_word_count(Str::ascii($libelle)) < $minMots) {
                $constats[] = "Libellé trop court (moins de {$minMots} mots)";
            }
            if (mb_strlen($libelle) > $cfg['max_caracteres']) {
                $constats[] = "Libellé trop long (plus de {$cfg['max_caracteres']} caractères)";
            }
            if ($parent !== null && $norm === $this->normaliser($parent)) {
                $constats[] = 'Reprend mot pour mot le libellé du niveau supérieur';
            }
            if ($doublon) {
                if (isset($this->vus[$niveau][$norm])) {
                    $constats[] = 'Libellé en double dans ce sous-programme';
                } else {
                    $this->vus[$niveau][$norm] = true;
                }
            }
            if (in_array($niveau, $cfg['niveaux_infinitif'], true) && !$this->commenceParInfinitif($libelle)) {
                $constats[] = 'Ne commence pas par un verbe à l\'infinitif (ex. « Organiser… », « Former… »)';
            }
            if ($niveau === 'Extrant' && $cfg['extrant_produit'] && $this->commenceParInfinitif($libelle)) {
                $constats[] = 'Formulé comme une action : un extrant décrit le produit obtenu (ex. « Équipements installés »)';
            }
        }

        foreach ($constats as $constat) {
            $this->observer($niveau, $libelle ?: '(vide)', $constat);
        }

        return $constats;
    }

    protected function commenceParInfinitif(string $libelle): bool
    {
        $premier = strtok($this->normaliser($libelle), ' ');

        if ($premier === false || in_array($premier, config('planification.libelles.faux_infinitifs'), true)) {
            return false;
        }

        return (bool) preg_match('/(er|ir|re|oir)$/', $premier);
    }

    protected function normaliser(string $texte): string
    {
        return Str::of($texte)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    protected function observer(string $niveau, string $libelle, string $constat): void
    {
        $this->observations[] = ['niveau' => $niveau, 'libelle' => $libelle, 'constat' => $constat];
    }

    protected function ligneVide(array $ctx, string $message, int $colspan): array
    {
        return ['ctx' => $ctx, 'message' => $message, 'message_colspan' => $colspan] + self::VIDE;
    }

    protected function codeLibelle(?string $code, ?string $libelle): string
    {
        return trim(($code ? "{$code} - " : '') . $libelle);
    }
}
