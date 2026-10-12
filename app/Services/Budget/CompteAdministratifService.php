<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\CbmtLigne;
use App\Models\ClotureExercice;
use App\Models\ClotureLigne;
use App\Models\Exercice;
use App\Models\LignePrevisionRecette;
use App\Models\LigneBudgetaire;
use App\Models\PrevisionRecette;
use App\Services\Programmation\TitreNomenclatureService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compte administratif d'un exercice (modèle standard) :
 *  1. présentation générale ;
 *  2. exécution des recettes (par titre, par ligne) ;
 *  3. exécution des dépenses (par titre, par sous-programme, par ligne) ;
 *  4. résultat de l'exercice ;
 *  5. annexes : états et taux de clôture, mouvements de crédits, collectifs, reports et annulations.
 * Réutilise les calculs existants (situation de clôture, états de clôture, mouvements) : mêmes chiffres partout.
 */
class CompteAdministratifService
{
    public function __construct(
        protected ClotureExerciceService $cloture,
        protected EtatsClotureService $etats,
        protected EtatMouvementsCreditsService $mouvements,
        protected MouvementCreditService $mouvementCredit,
        protected TitreNomenclatureService $titres,
    ) {}

    public function construire(Exercice $exercice, Budget $budget): array
    {
        $depenses = $this->depenses($exercice, $budget);
        $recettes = $this->recettes($exercice);

        $totD = $this->totaux($depenses, ['initial', 'collectifs', 'virements', 'definitif', 'engage', 'liquide', 'ordonnance', 'paye', 'reste_a_payer', 'report', 'annule']);
        $totR = $this->totaux($recettes, ['initial', 'collectifs', 'definitif', 'attendu', 'recouvre', 'rar']);

        $restesAPayer = $totD['reste_a_payer'];
        $resultatCaisse = $totR['recouvre'] - $totD['paye'];

        return [
            'exercice' => $exercice,
            'budget'   => $budget,
            'cloture'  => ClotureExercice::where('exercice_id', $exercice->id)->where('budget_id', $budget->id)->first(),
            'recettes' => [
                'lignes'   => $recettes,
                'parTitre' => $this->parCle(
                    $recettes,
                    'titre',
                    fn($t) => $t ? "Titre {$t} — " . (CbmtLigne::TITRES_RESSOURCES[$t] ?? '') : 'Sans titre',
                    ['initial', 'collectifs', 'definitif', 'attendu', 'recouvre', 'rar']
                ),
                'totaux'   => $totR,
            ],
            'depenses' => [
                'lignes'   => $depenses,
                'parTitre' => $this->parCle(
                    $depenses,
                    'titre',
                    fn($t) => $t ? "Titre {$t} — " . (CbmtLigne::TITRES_DEPENSES[$t] ?? '') : 'Sans titre',
                    ['initial', 'collectifs', 'virements', 'definitif', 'engage', 'liquide', 'ordonnance', 'paye', 'reste_a_payer', 'report', 'annule']
                ),
                'parSousProgramme' => $this->parCle(
                    $depenses,
                    'sous_programme',
                    fn($sp) => $sp ?: 'Sous-programme non déterminé',
                    ['definitif', 'engage', 'paye', 'reste_a_payer', 'report', 'annule']
                ),
                'totaux'   => $totD,
            ],
            'taux' => [
                'execution_recettes' => $totR['definitif'] > 0 ? round($totR['recouvre'] / $totR['definitif'] * 100, 2) : null,
                'engagement'         => $totD['definitif'] > 0 ? round($totD['engage'] / $totD['definitif'] * 100, 2) : null,
                'paiement'           => $totD['definitif'] > 0 ? round($totD['paye'] / $totD['definitif'] * 100, 2) : null,
            ],
            'resultat' => [
                'recettes_recouvrees' => $totR['recouvre'],
                'depenses_payees'     => $totD['paye'],
                'resultat_caisse'     => $resultatCaisse,
                'restes_a_recouvrer'  => $totR['rar'],
                'restes_a_payer'      => $restesAPayer,
                'resultat_corrige'    => $resultatCaisse + $totR['rar'] - $restesAPayer,
            ],
            'annexes' => [
                'etats'      => $this->etats->calculer($exercice, $budget),
                'mouvements' => $this->mouvements->construire($budget, null, null, true, null, true),
            ],
        ];
    }

    /** Exécution des dépenses, ligne par ligne. */
    protected function depenses(Exercice $exercice, Budget $budget): Collection
    {
        $cloture = ClotureExercice::where('exercice_id', $exercice->id)->where('budget_id', $budget->id)->first();
        $lignesCloture = $cloture ? ClotureLigne::where('cloture_exercice_id', $cloture->id)->get()->keyBy('ligne_budgetaire_id') : collect();

        return LigneBudgetaire::withoutGlobalScope('exercice')
            ->where('budget_id', $budget->id)
            ->with(['nomenclature', 'budget'])
            ->get()
            ->map(function (LigneBudgetaire $l) use ($lignesCloture) {
                $s = $this->cloture->situation($l);
                $initial = (float) $l->budget_initial;
                $virements = (float) ($l->virements_entrants ?? 0) - (float) ($l->virements_sortants ?? 0);
                $definitif = $s['dotation'];
                $c = $lignesCloture->get($l->id);
                $report = (float) ($c?->report_retenu ?? 0);

                return [
                    'code'           => $l->nomenclature?->code,
                    'libelle'        => $l->nomenclature?->libelle,
                    'titre'          => $this->titres->titreDe($l->nomenclature),
                    'sous_programme' => ($sp = $this->mouvementCredit->sousProgrammeDe($l)) ? "{$sp->code} — {$sp->libelle}" : null,
                    'initial'        => $initial,
                    'virements'      => $virements,
                    'collectifs'     => round($definitif - $initial - $virements, 2),
                    'definitif'      => $definitif,
                    'engage'         => $s['engage'],
                    'liquide'        => $s['liquide'],
                    'ordonnance'     => $s['ordonnance'],
                    'paye'           => $s['paye'],
                    'reste_a_payer'  => max(0, $s['ordonnance'] - $s['paye']),
                    'report'         => $report,
                    'annule'         => $c ? (float) $c->annule : max(0, $definitif - $s['paye'] - $report),
                ];
            })
            ->filter(fn($l) => $l['definitif'] != 0 || $l['engage'] != 0 || $l['paye'] != 0)
            ->sortBy('code')->values();
    }

    /** Exécution des recettes, ligne par ligne. */
    protected function recettes(Exercice $exercice): Collection
    {
        $prevision = PrevisionRecette::withoutGlobalScope('exercice')->where('exercice_id', $exercice->id)
            ->where('statut', '!=', 'elaboration')->orderByRaw("CASE WHEN statut = 'adopte' THEN 0 ELSE 1 END")->latest('id')->first();

        if (!$prevision) {
            return collect();
        }

        // Attendu (constaté) par ligne : Σ montant_constate des recettes réelles
        $attendus = DB::table('recettes_reelles as r')
            ->join('previsions_recettes_mensuelles as m', 'm.id', '=', 'r.prevision_recette_mensuelle_id')
            ->whereNull('r.deleted_at')->where('r.statut', '!=', 'prevue')
            ->groupBy('m.ligne_prevision_recette_id')
            ->selectRaw('m.ligne_prevision_recette_id as ligne, SUM(CAST(COALESCE(r.montant_constate, r.montant) AS FLOAT)) as attendu, SUM(CAST(r.montant AS FLOAT)) as recouvre')
            ->get()->keyBy('ligne');

        return LignePrevisionRecette::where('prevision_recette_id', $prevision->id)->get()
            ->map(function ($l) use ($attendus) {
                $initial = (float) $l->montant_prevu_initial;
                $definitif = $l->getMontantRectifieReel();
                $a = $attendus->get($l->id);
                $recouvre = (float) ($a->recouvre ?? $l->montant_recouvre);
                $attendu = (float) ($a->attendu ?? $recouvre);

                return [
                    'code' => $l->code_nomenclature,
                    'libelle' => $l->libelle_nomenclature,
                    'titre' => $this->titres->titrePropose($l->code_nomenclature, 'recette'),
                    'initial' => $initial,
                    'collectifs' => round($definitif - $initial, 2),
                    'definitif' => $definitif,
                    'attendu' => $attendu,
                    'recouvre' => $recouvre,
                    'rar' => max(0, $attendu - $recouvre),
                ];
            })
            ->filter(fn($l) => $l['definitif'] != 0 || $l['recouvre'] != 0)
            ->sortBy('code')->values();
    }

    protected function totaux(Collection $lignes, array $colonnes): array
    {
        return collect($colonnes)->mapWithKeys(fn($c) => [$c => (float) $lignes->sum($c)])->all();
    }

    protected function parCle(Collection $lignes, string $cle, callable $libelle, array $colonnes): Collection
    {
        return $lignes->groupBy(fn($l) => $l[$cle] ?? 0)->sortKeys()
            ->map(fn($g, $k) => ['libelle' => $libelle($k ?: null)] + $this->totaux($g, $colonnes))
            ->values();
    }
}
