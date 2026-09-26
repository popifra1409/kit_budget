<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcul unique de l'"Etat des disponibilites budgetaires", utilise par
 * l'export PDF ET l'export Excel (ils ne peuvent donc pas diverger).
 *
 * Regles (identiques a l'etat historique) :
 *  - Engage          = montant_engage des engagements dont la nomenclature
 *                      principale est celle de la ligne
 *  - Ordonne         = montant_engage des engagements ayant au moins une OP
 *  - Paye            = OP 'standard' au statut 'payee' (montant_net)
 *  - Taxes reversees = OP 'impot' au statut 'payee' (montant_net)
 *  - Tx Engagement   = Engage / Budget rectifie
 *  - Tx Ordonnanct.  = Ordonne / Engage
 *  - Tx Execution    = (Paye + Taxes reversees) / Budget rectifie
 *
 * Chaque engagement etant rattache a UNE ligne (nomenclature principale),
 * la somme des engagements affiches sous une ligne est egale a la ligne.
 */
class EtatDisponibilitesService
{
    /** Colonnes additionnables (les taux sont recalcules, jamais additionnes). */
    public const MONTANTS = [
        'budgetInitial',
        'virementsEntrants',
        'virementsSortants',
        'budgetRectifie',
        'engage',
        'ordonne',
        'paye',
        'taxesReversees',
        'disponibleEng',
        'disponibleOrd',
    ];

    public function construire(
        Budget $budget,
        bool $detaille = false,
        bool $engageesSeulement = false,
        ?int $programmeId = null
    ): array {
        $lignes = $budget->lignesBudgetaires()->with('nomenclature')->get();

        // Tous les engagements du budget et leurs OP en UNE requete (au lieu de 4 par ligne)
        $engagementsParNomenclature = $this->chargerEngagements($budget)
            ->groupBy('nomenclature_principale_id');

        $groupes = [];
        $totalGeneral = $this->totalVide();
        $nbLignes = 0;
        $nbEngagements = 0;
        $libelleProgrammeFiltre = null;

        foreach ($lignes as $ligne) {
            $engagements = $engagementsParNomenclature
                ->get($ligne->nomenclature_id, collect())
                ->map(fn(Engagement $e) => $this->detailEngagement($e))
                ->values();

            $c = $this->calculerLigne($ligne, $engagements);

            if ($engageesSeulement && $c['engage'] <= 0) {
                continue;
            }

            $classification = $ligne->getClassificationStrategique();
            $programme = $classification['programme'] ?? null;
            $sousProgramme = $classification['sous_programme'] ?? null;

            if ($programmeId && $programme?->id !== $programmeId) {
                continue;
            }

            $cleP = $programme ? 'p' . $programme->id : 'non_affecte';
            $cleSp = $sousProgramme ? 'sp' . $sousProgramme->id : 'sans';

            $groupes[$cleP] ??= [
                'programme'    => $programme,
                'libelle'      => $programme ? "{$programme->code} — {$programme->libelle}" : 'NON AFFECTÉ À UN PROGRAMME',
                'ordre'        => $programme?->code ?? 'ZZZZ', // "non affecte" en dernier
                'sous_groupes' => [],
                'total'        => $this->totalVide(),
            ];

            $groupes[$cleP]['sous_groupes'][$cleSp] ??= [
                'sous_programme' => $sousProgramme,
                'libelle'        => $sousProgramme ? "{$sousProgramme->code} — {$sousProgramme->libelle}" : null,
                'lignes'         => [],
                'total'          => $this->totalVide(),
            ];

            $groupes[$cleP]['sous_groupes'][$cleSp]['lignes'][] = [
                'code'        => $ligne->nomenclature?->code ?? '',
                'libelle'     => $ligne->nomenclature?->libelle ?? '',
                'c'           => $c,
                'engagements' => $detaille ? $engagements->all() : [],
            ];

            $this->ajouter($groupes[$cleP]['sous_groupes'][$cleSp]['total'], $c);
            $this->ajouter($groupes[$cleP]['total'], $c);
            $this->ajouter($totalGeneral, $c);

            $nbLignes++;
            $nbEngagements += $engagements->count();
            $libelleProgrammeFiltre ??= $programmeId ? $groupes[$cleP]['libelle'] : null;
        }

        // Tri : programmes par code ("non affecte" en dernier), lignes par code
        uasort($groupes, fn($a, $b) => strcmp((string) $a['ordre'], (string) $b['ordre']));

        foreach ($groupes as &$groupe) {
            foreach ($groupe['sous_groupes'] as &$sousGroupe) {
                usort($sousGroupe['lignes'], fn($a, $b) => strcmp($a['code'] ?: 'ZZZZ', $b['code'] ?: 'ZZZZ'));
                $sousGroupe['total'] = $this->finaliser($sousGroupe['total']);
            }
            unset($sousGroupe);

            $groupe['sous_groupes'] = array_values($groupe['sous_groupes']);
            $groupe['total'] = $this->finaliser($groupe['total']);

            // Sous-total programme utile seulement s'il n'est pas identique a un unique sous-total SP
            $groupe['afficher_total'] = count($groupe['sous_groupes']) > 1
                || empty($groupe['sous_groupes'][0]['libelle']);
        }
        unset($groupe);

        return [
            'budget'             => $budget,
            'detaille'           => $detaille,
            'engagees_seulement' => $engageesSeulement,
            'programme_filtre'   => $libelleProgrammeFiltre,
            'groupes'            => array_values($groupes),
            'total'              => $this->finaliser($totalGeneral),
            'nb_lignes_affichees' => $nbLignes,
            'nb_lignes_budget'   => $lignes->count(),
            'budget_total'       => (float) $lignes->sum(fn($l) => (float) $l->budget_rectifie),
            'nb_engagements'     => $nbEngagements,
        ];
    }

    // ────────────────────────────────────────────────────────────────

    protected function chargerEngagements(Budget $budget): Collection
    {
        $relations = [
            // OP annulees exclues (le statut 'annulee' est prevu par le reste de l'application)
            'ordonnancesPaiement' => fn($q) => $q->where('statut', '!=', 'annulee'),
        ];

        // Evite une requete par engagement pour le nom du beneficiaire, si la relation existe
        if (method_exists(Engagement::class, 'beneficiaire')) {
            $relations[] = 'beneficiaire';
        }

        return Engagement::withoutGlobalScope('exercice') // le budget_id suffit a cibler l'exercice
            ->where('budget_id', $budget->id)
            ->whereNull('date_annulation')                 // engagements annules exclus
            ->with($relations)
            ->orderBy('date_engagement')
            ->orderBy('numero')
            ->get();
    }

    protected function detailEngagement(Engagement $e): array
    {
        $ops = $e->ordonnancesPaiement;
        $standard = $ops->where('type_ordonnance', 'standard');

        return [
            'numero'         => $e->numero,
            'date'           => $e->date_engagement ? Carbon::parse($e->date_engagement)->format('d/m/Y') : '',
            'beneficiaire'   => $e->getNomBeneficiaire() ?? '-',
            'objet'          => (string) ($e->objet ?? ''),
            'numeros_op'     => $standard->pluck('numero')->filter()->implode(', '),
            'statut'         => $e->statut,
            'engage'         => (float) $e->montant_engage,
            'ordonne'        => $ops->isNotEmpty() ? (float) $e->montant_engage : 0.0,
            'paye'           => (float) $standard->where('statut', 'payee')->sum('montant_net'),
            'taxesReversees' => (float) $ops->where('type_ordonnance', 'impot')->where('statut', 'payee')->sum('montant_net'),
        ];
    }

    protected function calculerLigne(LigneBudgetaire $ligne, Collection $engagements): array
    {
        $c = [
            'budgetInitial'     => (float) ($ligne->budget_initial ?? 0),
            'virementsEntrants' => (float) ($ligne->virements_entrants ?? 0),
            'virementsSortants' => (float) ($ligne->virements_sortants ?? 0),
        ];

        // Budget rectifie stocke (integre les collectifs budgetaires), sinon recalcule
        $c['budgetRectifie'] = (float) ($ligne->budget_rectifie
            ?? ($c['budgetInitial'] + $c['virementsEntrants'] - $c['virementsSortants']));

        foreach (['engage', 'ordonne', 'paye', 'taxesReversees'] as $cle) {
            $c[$cle] = (float) $engagements->sum($cle);
        }

        $c['disponibleEng'] = $c['budgetRectifie'] - $c['engage'];
        $c['disponibleOrd'] = $c['budgetRectifie'] - $c['ordonne'];

        return $this->finaliser($c);
    }

    protected function finaliser(array $t): array
    {
        $br = $t['budgetRectifie'];

        $t['tauxEngagement']     = $br > 0 ? ($t['engage'] / $br) * 100 : 0;
        $t['tauxOrdonnancement'] = $t['engage'] > 0 ? ($t['ordonne'] / $t['engage']) * 100 : 0;
        $t['tauxExecution']      = $br > 0 ? (($t['paye'] + $t['taxesReversees']) / $br) * 100 : 0;

        return $t;
    }

    protected function ajouter(array &$total, array $c): void
    {
        foreach (self::MONTANTS as $cle) {
            $total[$cle] += $c[$cle];
        }
    }

    protected function totalVide(): array
    {
        return array_fill_keys(self::MONTANTS, 0.0);
    }
}
