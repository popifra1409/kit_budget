<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\ClotureExercice;
use App\Models\ClotureLigne;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Models\OrdonnancePaiement;
use App\Services\ParametresExecution;
use App\Services\Programmation\TitreNomenclatureService;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Clôture d'exercice — reports de crédits et annulations de fin de gestion.
 *
 * Par ligne :  dotation actualisée (CP ouverts) = payé + report retenu + annulé
 *  - engagé non payé  = engagé − payé (liquidé non décaissé, en attente, investissement sans service fait) ;
 *  - report proposé   = engagé non payé, si investissement (titre 5) ou si le paramètre
 *                       « report_cp_fonctionnement » l'autorise ; sinon 0 ;
 *  - report retenu    = modifiable, entre 0 et l'engagé non payé (même ligne, donc même sous-programme) ;
 *  - annulé           = dotation − payé − report retenu.
 * Circuit : préparation → arrêté de l'ordonnateur → avis conforme du CA → reprise en N+1 (étape 5b).
 */
class ClotureExerciceService
{
    public function __construct(
        protected TitreNomenclatureService $titres,
        protected MouvementCreditService $mouvements,
    ) {}

    // ════════════════════════════════════════════════════════
    // PRÉPARATION ET CALCUL
    // ════════════════════════════════════════════════════════

    /** Crée (ou recalcule) la clôture d'un budget d'exercice. */
    public function preparer(Exercice $exercice, Budget $budget): ClotureExercice
    {
        $cloture = ClotureExercice::firstOrCreate(
            ['exercice_id' => $exercice->id, 'budget_id' => $budget->id],
            ['statut' => 'preparation']
        );

        $this->calculer($cloture);

        return $cloture;
    }

    /**
     * (Re)calcule toutes les lignes. Un report modifié à la main est CONSERVÉ s'il reste valide ;
     * sinon le report proposé est retenu. Possible uniquement en préparation.
     */
    public function calculer(ClotureExercice $cloture): void
    {
        $this->exigerStatut($cloture, 'preparation');

        $exercice = $cloture->exercice;
        $dateReference = Carbon::create($exercice->annee, 12, 31);
        $fonctionnementReportable = (bool) ParametresExecution::get('report_cp_fonctionnement', $dateReference);

        DB::transaction(function () use ($cloture, $fonctionnementReportable) {
            $lignes = LigneBudgetaire::withoutGlobalScope('exercice')
                ->where('budget_id', $cloture->budget_id)
                ->with(['nomenclature', 'budget'])
                ->get();

            foreach ($lignes as $ligne) {
                $situation = $this->situation($ligne);
                $titre = $this->titres->titreDe($ligne->nomenclature);
                $reportable = $titre === 5 || $fonctionnementReportable;
                $propose = $reportable ? $situation['engage_non_paye'] : 0.0;

                $existante = ClotureLigne::where('cloture_exercice_id', $cloture->id)->where('ligne_budgetaire_id', $ligne->id)->first();

                // Report modifié à la main : conservé s'il reste dans les bornes
                $retenu = $existante && abs((float) $existante->report_retenu - (float) $existante->report_propose) > 0.01
                    ? min(max(0, (float) $existante->report_retenu), $situation['engage_non_paye'])
                    : $propose;

                ClotureLigne::updateOrCreate(
                    ['cloture_exercice_id' => $cloture->id, 'ligne_budgetaire_id' => $ligne->id],
                    $situation + [
                        'code'                 => $ligne->nomenclature?->code,
                        'libelle'              => $ligne->nomenclature?->libelle,
                        'titre'                => $titre,
                        'sous_programme_ep_id' => $this->mouvements->sousProgrammeDe($ligne)?->id,
                        'report_propose'       => $propose,
                        'report_retenu'        => $retenu,
                        'annule'               => max(0, $situation['dotation'] - $situation['paye'] - $retenu),
                    ]
                );
            }

            $cloture->update([
                'report_fonctionnement_autorise' => $fonctionnementReportable,
                'date_calcul'                    => now(),
            ]);

            $this->mettreAJourTotaux($cloture);
        });
    }

    /** Situation d'une ligne : dotation actualisée, engagé, liquidé, ordonnancé, payé. */
    public function situation(LigneBudgetaire $ligne): array
    {
        $engagements = Engagement::withoutGlobalScope('exercice')
            ->where('budget_id', $ligne->budget_id)
            ->where('nomenclature_principale_id', $ligne->nomenclature_id)
            ->whereNull('date_annulation')
            ->where('statut', '!=', 'annule');

        $ids = (clone $engagements)->pluck('id');
        $engage = (float) (clone $engagements)->sum('montant_engage');

        $ops = OrdonnancePaiement::query()->whereIn('engagement_id', $ids)->where('type_ordonnance', 'standard');

        // Payé (en crédits) : montant brut des OP standard payées (les retenues suivent l'OP impôt)
        $paye = (float) (clone $ops)->where('statut', 'payee')->sum('montant_brut');
        $ordonnance = (float) (clone $ops)->where('statut', '!=', 'annulee')->sum('montant_brut');
        $liquide = (float) Liquidation::whereIn('engagement_id', $ids)->whereIn('statut', ['liquidee', 'visee'])->sum('montant_liquide');

        return [
            'dotation'        => round((float) $ligne->getBudgetRectifieReel(), 2),
            'engage'          => round($engage, 2),
            'liquide'         => round($liquide, 2),
            'ordonnance'      => round($ordonnance, 2),
            'paye'            => round($paye, 2),
            'engage_non_paye' => round(max(0, $engage - $paye), 2),
        ];
    }

    /** Modification d'un report retenu (en préparation) : 0 ≤ report ≤ engagé non payé. */
    public function modifierReport(ClotureLigne $ligne, float $montant, ?string $motif = null): void
    {
        $cloture = $ligne->cloture;
        $this->exigerStatut($cloture, 'preparation');

        if ($montant < 0 || $montant > (float) $ligne->engage_non_paye + 0.01) {
            throw new DomainException('Le report doit être compris entre 0 et l\'engagé non payé ('
                . number_format((float) $ligne->engage_non_paye, 0, ',', ' ') . ' FCFA).');
        }

        if ($montant > 0 && (int) $ligne->titre !== 5 && !$cloture->report_fonctionnement_autorise) {
            throw new DomainException("Le report de CP n'est pas autorisé sur les dépenses de fonctionnement (paramètre d'exécution).");
        }

        $ligne->update([
            'report_retenu' => round($montant, 2),
            'annule'        => max(0, (float) $ligne->dotation - (float) $ligne->paye - $montant),
            'motif_ecart'   => abs($montant - (float) $ligne->report_propose) > 0.01 ? $motif : null,
        ]);

        $this->mettreAJourTotaux($cloture);
    }

    public function mettreAJourTotaux(ClotureExercice $cloture): void
    {
        $l = ClotureLigne::where('cloture_exercice_id', $cloture->id)->get();
        $dotation = (float) $l->sum('dotation');

        $cloture->update(['totaux' => [
            'dotation'             => $dotation,
            'engage'               => (float) $l->sum('engage'),
            'paye'                 => (float) $l->sum('paye'),
            'engage_non_paye'      => (float) $l->sum('engage_non_paye'),
            'report_propose'       => (float) $l->sum('report_propose'),
            'report_retenu'        => (float) $l->sum('report_retenu'),
            'annule'               => (float) $l->sum('annule'),
            'non_reporte_non_paye' => (float) $l->sum(fn($x) => $x->non_reporte_non_paye),
            'lignes_reportees'     => $l->where('report_retenu', '>', 0)->count(),
            'taux_annulation'      => $dotation > 0 ? round($l->sum('annule') / $dotation * 100, 2) : 0,
        ]]);
    }

    // ════════════════════════════════════════════════════════
    // CIRCUIT : ARRÊTÉ, AVIS DU CA
    // ════════════════════════════════════════════════════════

    /** Arrêté de report de l'ordonnateur : fige les reports. */
    public function arreter(ClotureExercice $cloture, array $acte): void
    {
        $this->exigerStatut($cloture, 'preparation');

        if (blank($acte['reference_arrete'] ?? null) || blank($acte['date_arrete'] ?? null)) {
            throw new DomainException("L'arrêté de report exige une référence et une date.");
        }

        // Si le décideur paramétré n'exige pas l'avis du CA, l'arrêté suffit (passage direct à l'étape suivante)
        $avisRequis = ParametresExecution::get('decideur_report') === 'ordonnateur_avis_ca';

        $cloture->update([
            'statut' => $avisRequis ? 'arretee' : 'avis_ca',
            'arrete_par' => auth()->id(),
            'reference_arrete' => $acte['reference_arrete'],
            'date_arrete' => $acte['date_arrete'],
            'piece_arrete' => $acte['piece_arrete'] ?? null,
        ]);

        ActivityLog::logAction($cloture, 'arrete_report', [
            'arrete' => $acte['reference_arrete'],
            'report' => $cloture->totaux['report_retenu'] ?? 0,
            'annule' => $cloture->totaux['annule'] ?? 0,
        ]);
    }

    /** Avis du CA : conforme → étape suivante ; défavorable → retour en préparation (reports à revoir). */
    public function enregistrerAvisCa(ClotureExercice $cloture, array $avis): void
    {
        $this->exigerStatut($cloture, 'arretee');

        $conforme = ($avis['avis_ca'] ?? null) === 'conforme';

        $cloture->update([
            'statut' => $conforme ? 'avis_ca' : 'preparation',
            'avis_ca' => $avis['avis_ca'],
            'reference_avis_ca' => $avis['reference_avis_ca'] ?? null,
            'date_avis_ca' => $avis['date_avis_ca'] ?? null,
            'piece_avis_ca' => $avis['piece_avis_ca'] ?? null,
            'observations' => trim(($cloture->observations ?? '') . ($conforme ? '' : "\nAvis défavorable du CA : " . ($avis['motif'] ?? ''))) ?: null,
        ]);

        ActivityLog::logAction($cloture, 'avis_ca_report', ['avis' => $avis['avis_ca'], 'reference' => $avis['reference_avis_ca'] ?? null]);
    }

    /** Retour en préparation avant l'avis du CA (correction de l'arrêté). */
    public function rouvrirPreparation(ClotureExercice $cloture, string $motif): void
    {
        $this->exigerStatut($cloture, 'arretee');

        $cloture->update(['statut' => 'preparation', 'observations' => trim(($cloture->observations ?? '') . "\nRetour en préparation : {$motif}")]);
        ActivityLog::logAction($cloture, 'rouvrir_preparation', ['motif' => $motif]);
    }

    protected function exigerStatut(ClotureExercice $cloture, string $statut): void
    {
        if ($cloture->statut !== $statut) {
            throw new DomainException("Action impossible : la clôture est « {$cloture->statut_label} ».");
        }
    }
}
