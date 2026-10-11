<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\CbmtLigne;
use App\Models\CollectifBudgetaire;
use App\Models\MouvementCollectif;
use App\Models\VirementBudgetaire;
use App\Services\ParametresExecution;
use App\Services\Programmation\TitreNomenclatureService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * État des mouvements de crédits d'un budget (document d'analyse et de présentation au CA) :
 *  1. synthèse et plafonds ;
 *  2. mouvements de gestion par type (fongibilité, virement, transfert) — tableau DÉBIT / CRÉDIT ;
 *  3. par sous-programme (cédés / reçus, fongibilité / virement) ;
 *  4. par titre de dépense ;
 *  5. collectifs budgétaires — tableau A-RECETTES / B-DÉPENSES, et par titre de recettes.
 * Un mouvement de crédits déplace à la fois des AE et des CP : AE = CP = montant.
 */
class EtatMouvementsCreditsService
{
    public function __construct(
        protected MouvementCreditService $mouvements,
        protected TitreNomenclatureService $titres,
    ) {}

    /**
     * @param array|null $collectifIds  collectifs à inclure (null = tous les collectifs adoptés de la période ; [] = aucun)
     */
    public function construire(
        Budget $budget,
        ?string $du = null,
        ?string $au = null,
        bool $executesSeulement = true,
        ?array $collectifIds = null,
        bool $inclureGestion = true
    ): array {
        // NB : sur Budget, « exercice » est une colonne (l'année), qui masque la relation du même nom.
        $statuts = $executesSeulement ? ['execute'] : ['approuve', 'execute'];

        // ── Mouvements de gestion (hors collectif) ──
        $gestion = VirementBudgetaire::withoutGlobalScope('exercice')
            ->where('budget_id', $budget->id)
            ->when(!$inclureGestion, fn($q) => $q->whereRaw('1 = 0'))
            ->where(fn($q) => $q->whereNull('origine')->orWhere('origine', '!=', 'collectif'))
            ->whereIn('statut', $statuts)
            ->when($du, fn($q) => $q->whereDate('date_virement', '>=', $du))
            ->when($au, fn($q) => $q->whereDate('date_virement', '<=', $au))
            ->with(['ligneSource.nomenclature', 'ligneDestination.nomenclature', 'sousProgrammeSource', 'sousProgrammeDestination'])
            ->orderBy('date_virement')->orderBy('numero')
            ->get()
            ->map(fn($v) => $this->ligneMouvement($v));

        $parType = collect(config('execution.types_mouvement', []))
            ->map(fn($def, $type) => [
                'type'       => $type,
                'libelle'    => $def['libelle'],
                'aide'       => $def['aide'] ?? '',
                'mouvements' => $gestion->where('type', $type)->values(),
                'total'      => (float) $gestion->where('type', $type)->sum('montant'),
            ])
            ->filter(fn($g) => $g['mouvements']->isNotEmpty())
            ->values();

        // ── Collectifs ──
        $collectifs = $collectifIds === [] ? collect() : $this->collectifs($budget, $du, $au, $collectifIds);

        // ── Plafonds ──
        $plafond = $this->mouvements->controlerPlafond($budget->id, 'virement', 0);

        return [
            'budget'      => $budget,
            'periode'     => [$du ? Carbon::parse($du) : null, $au ? Carbon::parse($au) : null],
            'statuts'     => $executesSeulement ? 'Mouvements exécutés' : 'Mouvements approuvés et exécutés',
            'inclureGestion' => $inclureGestion,
            'parType'     => $parType,
            'parSousProgramme' => $this->parSousProgramme($gestion),
            'parTitreDepense'  => $this->parTitreDepense($gestion, $collectifs),
            'collectifs'  => $collectifs,
            'parTitreRecette'  => $this->parTitreRecette($collectifs),
            'synthese'    => [
                'credits_ouverts'  => $plafond['credits_ouverts'],
                'base'             => $plafond['base'] === 'actualise' ? 'actualisés' : 'initiaux',
                'plafond_pct'      => $plafond['plafond_pct'],
                'plafond_montant'  => $plafond['plafond_montant'],
                'cumul_virements'  => $plafond['cumul_avant'],
                'taux_virements'   => $plafond['credits_ouverts'] > 0 ? round($plafond['cumul_avant'] / $plafond['credits_ouverts'] * 100, 2) : 0,
                'marge_virements'  => $plafond['plafond_montant'] - $plafond['cumul_avant'],
                'fongibilites'     => (float) $gestion->where('type', 'fongibilite')->sum('montant'),
                'transferts'       => (float) $gestion->where('type', 'transfert')->sum('montant'),
                'nb_mouvements'    => $gestion->count(),
                'plafond_annulations_pct' => (float) ParametresExecution::get('plafond_annulations_pct'),
                'collectifs' => [
                    'nombre'            => $collectifs->count(),
                    'augmentations_dep' => (float) $collectifs->sum('augmentations_depenses'),
                    'reductions_dep'    => (float) $collectifs->sum('reductions_depenses'),
                    'virements'         => (float) $collectifs->sum('total_virements'),
                    'recettes'          => (float) $collectifs->sum('total_recettes'),
                ],
            ],
        ];
    }

    /** LR (ligne de référence, existante) ou MN (mesure nouvelle : ligne créée par un collectif). */
    public static function lrMn($ligne): string
    {
        if (!$ligne) {
            return '';
        }

        return ($ligne->est_issue_collectif ?? false) ? 'MN' : 'LR';
    }

    /** Une ligne de mouvement : débit (ligne réduite) / crédit (ligne augmentée). */
    protected function ligneMouvement($v): array
    {
        $type = $this->mouvements->typeDe($v);

        return [
            'numero'      => $v->numero,
            'date'        => $v->date_virement,
            'type'        => $type,
            'montant'     => (float) $v->montant,
            'debit_code'  => $v->ligneSource?->nomenclature?->code,
            'debit_libelle' => $v->ligneSource?->nomenclature?->libelle,
            'debit_titre' => $this->titres->titreDe($v->ligneSource?->nomenclature),
            'debit_lrmn'  => static::lrMn($v->ligneSource),
            'credit_code' => $v->ligneDestination?->nomenclature?->code,
            'credit_libelle' => $v->ligneDestination?->nomenclature?->libelle,
            'credit_titre' => $this->titres->titreDe($v->ligneDestination?->nomenclature),
            'credit_lrmn'  => static::lrMn($v->ligneDestination),
            'sp_source'   => $v->sousProgrammeSource,
            'sp_destination' => $v->sousProgrammeDestination,
            'reference'   => $v->reference_decision,
            'motif'       => $v->motif,
        ];
    }

    /** Par sous-programme : cédé et reçu, en distinguant fongibilité (interne) et virement. */
    protected function parSousProgramme(Collection $gestion): Collection
    {
        $sp = [];
        $cle = fn($s) => $s ? $s->id : 0;
        $libelle = fn($s) => $s ? "{$s->code} — {$s->libelle}" : 'Sous-programme non déterminé';

        foreach ($gestion as $m) {
            foreach ([['sp_source', 'cede'], ['sp_destination', 'recu']] as [$champ, $sens]) {
                $k = $cle($m[$champ]);
                $sp[$k] ??= ['libelle' => $libelle($m[$champ]), 'fongibilite' => 0.0, 'cede_virement' => 0.0, 'recu_virement' => 0.0, 'transfert' => 0.0];

                if ($m['type'] === 'fongibilite') {
                    if ($sens === 'cede') $sp[$k]['fongibilite'] += $m['montant'];   // redéployé à l'intérieur
                } elseif ($m['type'] === 'transfert') {
                    $sp[$k]['transfert'] += $sens === 'cede' ? -$m['montant'] : $m['montant'];
                } else {
                    $sp[$k][$sens === 'cede' ? 'cede_virement' : 'recu_virement'] += $m['montant'];
                }
            }
        }

        return collect($sp)->map(fn($l) => $l + ['solde' => $l['recu_virement'] - $l['cede_virement'] + $l['transfert']])->sortBy('libelle')->values();
    }

    /** Par titre de dépense : réductions, augmentations, solde (gestion + collectifs). */
    protected function parTitreDepense(Collection $gestion, Collection $collectifs): Collection
    {
        $t = [];
        $ajouter = function (?int $titre, float $reduction, float $augmentation) use (&$t) {
            $k = $titre ?? 0;
            $t[$k] ??= ['titre' => $titre, 'reduction' => 0.0, 'augmentation' => 0.0];
            $t[$k]['reduction'] += $reduction;
            $t[$k]['augmentation'] += $augmentation;
        };

        foreach ($gestion as $m) {
            $ajouter($m['debit_titre'], $m['montant'], 0);
            $ajouter($m['credit_titre'], 0, $m['montant']);
        }
        foreach ($collectifs as $c) {
            foreach ($c['depenses'] as $d) {
                $d['montant'] < 0 ? $ajouter($d['titre'], abs($d['montant']), 0) : $ajouter($d['titre'], 0, $d['montant']);
            }
            foreach ($c['virements'] as $v) {
                $ajouter($v['debit_titre'], $v['montant'], 0);
                $ajouter($v['credit_titre'], 0, $v['montant']);
            }
        }

        return collect($t)->sortKeys()->map(fn($l) => $l + [
            'libelle' => $l['titre'] ? "Titre {$l['titre']} — " . (CbmtLigne::TITRES_DEPENSES[$l['titre']] ?? '') : 'Sans titre',
            'solde'   => $l['augmentation'] - $l['reduction'],
        ])->values();
    }

    /** Par titre de recettes (collectifs). */
    protected function parTitreRecette(Collection $collectifs): Collection
    {
        return $collectifs->flatMap(fn($c) => $c['recettes'])
            ->groupBy(fn($r) => $r['titre'] ?? 0)
            ->sortKeys()
            ->map(fn($g, $titre) => [
                'libelle'      => $titre ? "Titre {$titre} — " . (CbmtLigne::TITRES_RESSOURCES[$titre] ?? '') : 'Sans titre',
                'augmentation' => (float) $g->where('montant', '>', 0)->sum('montant'),
                'reduction'    => (float) abs($g->where('montant', '<', 0)->sum('montant')),
                'solde'        => (float) $g->sum('montant'),
            ])->values();
    }

    /** Collectifs adoptés de l'exercice : recettes, dépenses (augmentations / réductions) et virements. */
    protected function collectifs(Budget $budget, ?string $du, ?string $au, ?array $ids = null): Collection
    {
        return CollectifBudgetaire::where('exercice_id', $budget->exercice_id)
            ->where('statut', 'adopte')
            ->when($ids !== null, fn($q) => $q->whereIn('id', $ids))
            ->when($ids === null && $du, fn($q) => $q->whereDate('date_collectif', '>=', $du))
            ->when($ids === null && $au, fn($q) => $q->whereDate('date_collectif', '<=', $au))
            ->orderBy('date_collectif')
            ->get()
            ->map(function ($c) {
                $mouvements = MouvementCollectif::where('collectif_budgetaire_id', $c->id)
                    ->whereNull('date_annulation')
                    ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
                    ->with(['ligneDepense.nomenclature', 'nouvelleLigneDepense.nomenclature', 'ligneRecette', 'nouvelleLigneRecette', 'virementBudgetaire.ligneSource.nomenclature', 'virementBudgetaire.ligneDestination.nomenclature'])
                    ->get();

                $recettes = $mouvements->where('type', 'recette')->map(function ($m) {
                    $l = $m->ligneRecette ?? $m->nouvelleLigneRecette;
                    return [
                        'code' => $l?->code_nomenclature,
                        'libelle' => $l?->libelle_nomenclature,
                        'titre' => $this->titres->titrePropose($l?->code_nomenclature, 'recette'),
                        'montant' => (float) $m->montant_modification,
                        'lrmn' => $m->nouvelle_ligne_recette_id ? 'MN' : 'LR',
                    ];
                })->values();

                $depenses = $mouvements->where('type', 'depense')->map(function ($m) {
                    $l = $m->ligneDepense ?? $m->nouvelleLigneDepense;
                    return [
                        'code' => $l?->nomenclature?->code,
                        'libelle' => $l?->nomenclature?->libelle,
                        'titre' => $this->titres->titreDe($l?->nomenclature),
                        'montant' => (float) $m->montant_modification,
                        'nouvelle' => (bool) $m->nouvelle_ligne_depense_id,
                        'lrmn' => $m->nouvelle_ligne_depense_id ? 'MN' : 'LR',
                    ];
                })->values();

                $virements = $mouvements->where('type', 'virement')->filter(fn($m) => $m->virementBudgetaire)
                    ->map(fn($m) => $this->ligneMouvement($m->virementBudgetaire))->values();

                return [
                    'collectif'  => $c,
                    'recettes'   => $recettes,
                    'depenses'   => $depenses,
                    'virements'  => $virements,
                    'total_recettes'         => (float) $recettes->sum('montant'),
                    'total_depenses'         => (float) $depenses->sum('montant'),
                    'augmentations_depenses' => (float) $depenses->where('montant', '>', 0)->sum('montant'),
                    'reductions_depenses'    => (float) abs($depenses->where('montant', '<', 0)->sum('montant')),
                    'total_virements'        => (float) $virements->sum('montant'),
                    'equilibre'              => round((float) $recettes->sum('montant') - (float) $depenses->sum('montant'), 2),
                ];
            });
    }
}
