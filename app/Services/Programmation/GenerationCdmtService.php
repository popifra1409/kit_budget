<?php
// app/Services/Programmation/GenerationCdmtService.php

namespace App\Services\Programmation;

use App\Models\CdmtExercice;
use App\Models\CdmtLigne;
use App\Models\SousProgrammeEp;
use App\Models\Tache;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Génère le CDMT par TÂCHE × CODE BUDGÉTAIRE à partir des sous-tâches de l'exercice de référence (N) :
 *  - une ligne par sous-tâche portant un compte, rattachée à son activité, son action et son
 *    sous-programme (règle générique programmesPourExercice) ;
 *  - AE/CP de N : ceux de la sous-tâche (concordants avec la dotation actualisée des lignes),
 *    rafraîchis à chaque génération ;
 *  - N+1..N+3 : reconduction de N à la création seulement (les projections saisies ne sont pas écrasées,
 *    sauf réinitialisation demandée) ; les mesures nouvelles (MN) ne sont jamais modifiées ;
 *  - investissement (titre 5) : maturité « en cours » par défaut (ligne déjà au budget N), à confirmer.
 */
class GenerationCdmtService
{
    public function __construct(protected TitreNomenclatureService $titres) {}

    /** programme_id => sous-programme, pour un exercice. */
    protected function carteSousProgrammes(int $exerciceId): array
    {
        $carte = [];
        foreach (SousProgrammeEp::all() as $sp) {
            foreach ($sp->programmesPourExercice($exerciceId) as $programmeId) {
                $carte[$programmeId] = $sp->id;
            }
        }

        return $carte;
    }

    /** Sous-tâches de N portant un compte mais sans AE ni CP (non reprises au CDMT). */
    public function sousTachesSansMontant(CdmtExercice $cdmt)
    {
        $exerciceId = $cdmt->cbmtExercice?->exerciceReference?->id;

        return $exerciceId
            ? Tache::withoutGlobalScope('exercice')->where('exercice_id', $exerciceId)->porteusesBudget()   // ✅ sous-tâches + tâches liées directement
            ->whereNotNull('nomenclature_id')
            ->where(fn($q) => $q->whereNull('ae')->orWhere('ae', '<=', 0))
            ->where(fn($q) => $q->whereNull('cp')->orWhere('cp', '<=', 0))
            ->orderBy('code')->get()
            : collect();
    }

    /** @return array{creees: int, mises_a_jour: int, sans_sous_programme: int, ignorees_nulles: int} */
    public function generer(CdmtExercice $cdmt, bool $reinitialiserProjections = false, bool $ignorerNulles = true): array
    {
        if (method_exists($cdmt, 'estModifiable') && !$cdmt->estModifiable()) {
            throw new DomainException("Ce CDMT n'est plus modifiable (transmis ou validé).");
        }

        $exercice = $cdmt->cbmtExercice?->exerciceReference;
        if (!$exercice) {
            throw new DomainException("Le CBMT de rattachement n'a pas d'exercice de référence (N).");
        }

        $carte = $this->carteSousProgrammes($exercice->id);
        $bilan = ['creees' => 0, 'mises_a_jour' => 0, 'sans_sous_programme' => 0, 'ignorees_nulles' => 0];

        $sousTaches = Tache::withoutGlobalScope('exercice')
            ->where('exercice_id', $exercice->id)
            ->porteusesBudget()   // ✅ sous-tâches + tâches liées directement
            ->whereNotNull('nomenclature_id')
            ->with(['nomenclature', 'parent', 'activite' => fn($q) => $q->withoutGlobalScope('exercice')->with(['action' => fn($a) => $a->withoutGlobalScope('exercice')])])
            ->orderBy('code')
            ->get();

        DB::transaction(function () use ($cdmt, $sousTaches, $carte, $reinitialiserProjections, $ignorerNulles, &$bilan) {
            foreach ($sousTaches as $t) {
                $ae = (float) $t->ae;
                $cp = (float) $t->cp;

                $ligne = CdmtLigne::firstOrNew(['cdmt_exercice_id' => $cdmt->id, 'tache_id' => $t->id]);

                if (!$ligne->exists && $ignorerNulles && abs($ae) < 1 && abs($cp) < 1) {
                    $bilan['ignorees_nulles']++;
                    continue;
                }

                $action = $t->activite?->action;
                $spId = $carte[$action?->programme_id] ?? null;
                $titre = $this->titres->titreDe($t->nomenclature);
                $investissement = $titre === 5;
                $nouvelle = !$ligne->exists;

                $ligne->fill([
                    'sous_programme_ep_id' => $spId,
                    'action_id'            => $action?->id,
                    'activite_id'          => $t->activite_id,
                    'nomenclature_id'      => $t->nomenclature_id,
                    'code'                 => $t->nomenclature?->code,
                    'titre'                => $titre,
                    'libelle'              => trim(($t->parent?->libelle ? $t->parent->libelle . ' — ' : '') . $t->libelle),
                    'n_ae'                 => $ae,
                    'n_cp'                 => $cp,
                    'est_investissement'   => $investissement,
                    'ordre'                => (int) preg_replace('/\D/', '', (string) $t->nomenclature?->code),
                ]);

                if ($nouvelle) {
                    $ligne->nature = 'LR';
                    // Ligne déjà au budget N : en cours de réalisation par défaut (Guide 2025, Encadré 6 : à confirmer)
                    $ligne->maturite = $investissement ? 'en_cours' : 'non_requise';
                } elseif ($investissement && in_array($ligne->maturite, [null, '', 'non_requise'], true)) {
                    $ligne->maturite = 'en_cours';
                }

                if ($nouvelle || ($reinitialiserProjections && $ligne->nature !== 'MN')) {
                    foreach ([1, 2, 3] as $d) {
                        $ligne->{"n_plus_{$d}_ae"} = $ae;
                        $ligne->{"n_plus_{$d}_cp"} = $cp;
                    }
                }

                $ligne->save();

                $nouvelle ? $bilan['creees']++ : $bilan['mises_a_jour']++;
                if (!$spId) {
                    $bilan['sans_sous_programme']++;
                }
            }
        });

        return $bilan;
    }
}
