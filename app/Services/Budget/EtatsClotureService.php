<?php
// app/Services/Budget/EtatsClotureService.php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LignePrevisionRecette;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Models\OrdonnancePaiement;
use App\Models\PrevisionRecette;
use App\Models\RapportActiviteLigne;
use App\Models\RecetteReelle;
use App\Models\Tache;
use App\Services\ParametresExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * États et taux de fin de gestion d'un exercice (budget actif).
 *
 * États : RAR, DENO, reste à payer, arriérés, dette.
 * Taux  : engagement, liquidation, ordonnancement, paiement CP, recouvrement, réalisation physique.
 * Montants budgétaires en BRUT (les retenues fiscales suivent l'OP impôt).
 */
class EtatsClotureService
{
    public function calculer(Exercice $exercice, Budget $budget): array
    {
        $engagementIds = Engagement::withoutGlobalScope('exercice')
            ->where('budget_id', $budget->id)
            ->whereNull('date_annulation')->where('statut', '!=', 'annule')
            ->pluck('id');

        $engage = (float) Engagement::withoutGlobalScope('exercice')->whereIn('id', $engagementIds)->sum('montant_engage');

        $opStandard = OrdonnancePaiement::query()->whereIn('engagement_id', $engagementIds)
            ->where('type_ordonnance', 'standard')->where('statut', '!=', 'annulee');

        $ordonnance = (float) (clone $opStandard)->sum('montant_brut');
        $paye = (float) (clone $opStandard)->where('statut', 'payee')->sum('montant_brut');

        // ── Liquidation : liquidations arrêtées + OP émises sans liquidation (service fait implicite avant 2027)
        $liquidations = Liquidation::whereIn('engagement_id', $engagementIds)->whereIn('statut', ['liquidee', 'visee']);
        $liquideExplicite = (float) (clone $liquidations)->sum('montant_liquide');
        $liquideImplicite = (float) (clone $opStandard)->whereNull('liquidation_id')->sum('montant_brut');
        $liquide = $liquideExplicite + $liquideImplicite;

        // ── États ──────────────────────────────────────────────
        $liquidationsAvecOp = OrdonnancePaiement::whereNotNull('liquidation_id')->where('statut', '!=', 'annulee')->pluck('liquidation_id');
        $deno = (float) (clone $liquidations)->whereNotIn('id', $liquidationsAvecOp)->sum('montant_liquide');

        $resteAPayer = (clone $opStandard)->where('statut', '!=', 'payee');
        $rap = (float) (clone $resteAPayer)->sum('montant_brut');
        $rapNet = (float) (clone $resteAPayer)->sum('montant_net');

        $arrieres = (clone $resteAPayer)->whereNotNull('date_echeance_paiement')->whereDate('date_echeance_paiement', '<', today());
        $arriere = (float) (clone $arrieres)->sum('montant_brut');

        $dette = max(0, $engage - $paye);

        // RAR = Σ (recette attendue − recette encaissée), sur les recettes de l'exercice
        $rar = 0.0;
        $rarNombre = 0;
        if (Schema::hasColumn('recettes_reelles', 'montant_constate')) {
            $expression = RecetteReelle::sqlResteARecouvrer();
            $rarQuery = RecetteReelle::where('exercice_id', $exercice->id)->where('statut', '!=', 'prevue')->whereRaw("{$expression} > 0");
            $rar = (float) (clone $rarQuery)->sum(\Illuminate\Support\Facades\DB::raw($expression));
            $rarNombre = (clone $rarQuery)->count();
        }

        // ── Bases ──────────────────────────────────────────────
        $aeOuvertes = (float) Tache::withoutGlobalScope('exercice')->where('exercice_id', $exercice->id)->where('niveau', 'sous_tache')->sum('ae');
        $cpOuverts = (float) Tache::withoutGlobalScope('exercice')->where('exercice_id', $exercice->id)->where('niveau', 'sous_tache')->sum('cp');

        $baseCredits = ParametresExecution::get('base_taux_execution', Carbon::create($exercice->annee, 12, 31)) === 'initial'
            ? (float) LigneBudgetaire::withoutGlobalScope('exercice')->where('budget_id', $budget->id)->sum('budget_initial')
            : (float) app(HistoriqueLigneBudgetaireService::class)->syntheseBudgets([$budget->id])['budget_actualise'];

        // ── Recettes ───────────────────────────────────────────
        $prevision = PrevisionRecette::withoutGlobalScope('exercice')->where('exercice_id', $exercice->id)
            ->where('statut', '!=', 'elaboration')->orderByRaw("CASE WHEN statut = 'adopte' THEN 0 ELSE 1 END")->latest('id')->first();
        $lignesRecettes = $prevision ? LignePrevisionRecette::where('prevision_recette_id', $prevision->id) : null;
        $previsionsRecettes = $lignesRecettes ? (float) (clone $lignesRecettes)->sum('montant_rectifie') : 0.0;
        $encaissements = $lignesRecettes ? (float) (clone $lignesRecettes)->sum('montant_recouvre') : 0.0;

        // ── Réalisation physique (rapports d'activité de l'exercice) ──
        $physique = RapportActiviteLigne::query()
            ->where(fn($q) => $q->where('nature', 'physique')->orWhereNull('nature'))
            ->whereHas('tache', fn($q) => $q->withoutGlobalScope('exercice')->where('exercice_id', $exercice->id));
        $prevuPhysique = (float) (clone $physique)->sum('prevision');
        $realisePhysique = (float) (clone $physique)->sum('realisation');

        $taux = fn(float $num, float $den) => $den > 0 ? round($num / $den * 100, 2) : null;

        return [
            'exercice' => $exercice->annee,
            'budget'   => $budget->code,
            'etats' => [
                'rar'           => [
                    'montant' => $rar,
                    'nombre' => $rarNombre,
                    'note' => $rarNombre ? null : 'Aucun reste à recouvrer : toutes les recettes attendues sont encaissées.'
                ],
                'deno'          => ['montant' => $deno],
                'reste_a_payer' => ['montant' => $rap, 'net' => $rapNet, 'nombre' => (clone $resteAPayer)->count()],
                'arrieres'      => ['montant' => $arriere, 'nombre' => (clone $arrieres)->count()],
                'dette'         => [
                    'montant' => $dette,
                    'deno' => $deno,
                    'reste_a_payer' => $rap,
                    'engage_sans_service_fait' => max(0, $dette - $deno - $rap)
                ],
            ],
            'taux' => [
                'engagement'         => ['valeur' => $taux($engage, $aeOuvertes),       'numerateur' => $engage,          'base' => $aeOuvertes,        'libelle_base' => 'AE ouvertes'],
                'liquidation'        => ['valeur' => $taux($liquide, $baseCredits),     'numerateur' => $liquide,         'base' => $baseCredits,       'libelle_base' => 'Base de crédits retenue'],
                'ordonnancement'     => ['valeur' => $taux($ordonnance, $baseCredits),  'numerateur' => $ordonnance,      'base' => $baseCredits,       'libelle_base' => 'Base de crédits retenue'],
                'paiement'           => ['valeur' => $taux($paye, $cpOuverts),          'numerateur' => $paye,            'base' => $cpOuverts,         'libelle_base' => 'CP ouverts'],
                'recouvrement'       => ['valeur' => $taux($encaissements, $previsionsRecettes), 'numerateur' => $encaissements, 'base' => $previsionsRecettes, 'libelle_base' => 'Prévisions de recettes actualisées'],
                'realisation_physique' => ['valeur' => $taux($realisePhysique, $prevuPhysique), 'numerateur' => $realisePhysique, 'base' => $prevuPhysique, 'libelle_base' => 'Prévisions physiques (rapports d\'activité)'],
            ],
            'detail_liquidation' => ['explicite' => $liquideExplicite, 'implicite_op_sans_liquidation' => $liquideImplicite],
            'date_calcul' => now()->toDateTimeString(),
        ];
    }

    public const LIBELLES_TAUX = [
        'engagement'           => "Taux d'engagement",
        'liquidation'          => 'Taux de liquidation',
        'ordonnancement'       => "Taux d'ordonnancement",
        'paiement'             => 'Taux de paiement CP',
        'recouvrement'         => 'Taux de recouvrement',
        'realisation_physique' => 'Taux de réalisation physique',
    ];

    public const LIBELLES_ETATS = [
        'rar'           => 'RAR — Recettes certaines à recouvrer en N+1',
        'deno'          => 'DENO — Dépenses liquidées non ordonnancées',
        'reste_a_payer' => 'Reste à payer — OP émises non décaissées',
        'arrieres'      => 'Arriérés — échéance de paiement dépassée',
        'dette'         => 'Dette — engagements juridiquement constitués non payés',
    ];
}
