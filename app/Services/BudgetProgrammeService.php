<?php

namespace App\Services;

use App\Models\Exercice;
use App\Models\Engagement;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionRecette;
use App\Models\LignePrevisionRecette;
use App\Models\PrevisionBudgetProgramme;
use Illuminate\Support\Collection;

/**
 * ══════════════════════════════════════════════════════════
 * Service — Budget Programme Triennal
 *
 * Génère le tableau des dépenses ET des recettes sur le plan
 * triennal (N-2, N-1, N, N+1, N+2), organisé par groupe de
 * nomenclature (FONCT/AVANTAG/INVEST pour les dépenses,
 * RPROP/SUBV/EMPR/DONS pour les recettes).
 * ══════════════════════════════════════════════════════════
 */
class BudgetProgrammeService
{
    /**
     * Collecter toutes les données pour l'export — dépenses ET recettes
     *
     * @param int $anneeRef  Année de référence (ex: 2026)
     */
    public function collecterDonnees(int $anneeRef): array
    {
        $annees = [
            'n_2' => $anneeRef - 2,
            'n_1' => $anneeRef - 1,
            'n'   => $anneeRef,
            'n1'  => $anneeRef + 1,
            'n2'  => $anneeRef + 2,
        ];

        $exercices = Exercice::whereIn('annee', array_values($annees))->get()->keyBy('annee');

        return [
            'annees'          => $annees,
            'annee_reference' => $anneeRef,
            'depenses'        => $this->collecterSection('depense', $annees, $exercices, $anneeRef),
            'recettes'        => $this->collecterSection('recette', $annees, $exercices, $anneeRef),
        ];
    }

    /**
     * Construit les lignes pour un type donné ('depense' ou 'recette'),
     * groupées et triées par groupe de nomenclature.
     */
    private function collecterSection(string $type, array $annees, Collection $exercices, int $anneeRef): array
    {
        $nomenclatures = NomenclatureBudgetaire::query()
            ->where('actif', true)
            ->where('type', $type)
            ->with('groupe')
            ->orderBy('code')
            ->get();

        $lignes = [];

        foreach ($nomenclatures as $nomenclature) {
            $ligne = [
                'imputation'     => $nomenclature->code,
                'rubrique'       => $nomenclature->libelle,
                'niveau'         => strlen($nomenclature->code) <= 3 ? 'chapitre' : 'article',
                'groupe_id'      => $nomenclature->groupe_id,
                'groupe_libelle' => $nomenclature->groupe?->libelle ?? 'Non classées / Hors groupe',
                'groupe_ordre'   => $nomenclature->groupe?->ordre ?? 9999,
            ];

            foreach ($annees as $key => $annee) {
                $exercice = $exercices[$annee] ?? null;

                $prevision = $type === 'depense'
                    ? $this->getPrevision($nomenclature->id, $annee, $exercice?->id)
                    : $this->getPrevisionRecette($nomenclature->id, $annee, $exercice?->id);
                $ligne["prev_{$key}"] = $prevision;

                if ($annee <= $anneeRef) {
                    $realisation = $type === 'depense'
                        ? $this->getRealisation($nomenclature->id, $annee, $exercice?->id)
                        : $this->getRealisationRecette($nomenclature->id, $annee, $exercice?->id);
                    $ligne["real_{$key}"] = $realisation;
                    $ligne["taux_{$key}"] = $prevision > 0 ? round($realisation / $prevision, 4) : null;
                }
            }

            $ligne['total_n1_n2']   = ($ligne['prev_n1'] ?? 0) + ($ligne['prev_n2'] ?? 0);
            $ligne['total_n_n1_n2'] = ($ligne['prev_n'] ?? 0) + ($ligne['prev_n1'] ?? 0) + ($ligne['prev_n2'] ?? 0);

            $lignes[] = $ligne;
        }

        // Tri : groupe (ordre) puis code — garde le regroupement chapitre cohérent à l'intérieur
        usort($lignes, function ($a, $b) {
            return [$a['groupe_ordre'], $a['imputation']] <=> [$b['groupe_ordre'], $b['imputation']];
        });

        return [
            'lignes'        => $lignes,
            'total_general' => $this->calculerTotalGeneral($lignes, $annees),
        ];
    }

    /**
     * Prévision DÉPENSE (depuis PrevisionBudgetProgramme ou LigneBudgetaire)
     */
    private function getPrevision(int $nomenclatureId, int $annee, ?int $exerciceId): float
    {
        $prevision = PrevisionBudgetProgramme::where('nomenclature_id', $nomenclatureId)
            ->where('annee', $annee)
            ->where('type', 'prevision')
            ->where('categorie', 'depense')
            ->value('montant');

        if ($prevision !== null) return (float) $prevision;

        if ($exerciceId) {
            $prevision = \App\Models\LigneBudgetaire::where('nomenclature_id', $nomenclatureId)
                ->where('budget_id', function ($q) use ($exerciceId) {
                    $q->select('id')->from('budgets')
                        ->where('exercice_id', $exerciceId)->limit(1);
                })
                ->value('budget_initial');

            if ($prevision !== null) return (float) $prevision;
        }

        return 0.0;
    }

    /**
     * Réalisation DÉPENSE depuis les engagements réels
     */
    private function getRealisation(int $nomenclatureId, int $annee, ?int $exerciceId): float
    {
        if (!$exerciceId) return 0.0;

        $realisation = PrevisionBudgetProgramme::where('nomenclature_id', $nomenclatureId)
            ->where('annee', $annee)
            ->where('type', 'realisation')
            ->where('categorie', 'depense')
            ->value('montant');

        if ($realisation !== null) return (float) $realisation;

        $realisation = Engagement::where('exercice_id', $exerciceId)
            ->where('nomenclature_principale_id', $nomenclatureId)
            ->where('statut', 'definitif')
            ->sum('montant_engage');

        return (float) ($realisation ?? 0);
    }

    /**
     * Prévision RECETTE (depuis PrevisionBudgetProgramme ou LignePrevisionRecette)
     *
     * ⚠️ Suppose que LignePrevisionRecette::montant_prevu_initial est le montant
     *    prévisionnel initial (par analogie avec LigneBudgetaire::budget_initial).
     */
    private function getPrevisionRecette(int $nomenclatureId, int $annee, ?int $exerciceId): float
    {
        $prevision = PrevisionBudgetProgramme::where('nomenclature_id', $nomenclatureId)
            ->where('annee', $annee)
            ->where('type', 'prevision')
            ->where('categorie', 'recette')
            ->value('montant');

        if ($prevision !== null) return (float) $prevision;

        if ($exerciceId) {
            $previsionRecetteIds = PrevisionRecette::where('exercice_id', $exerciceId)->pluck('id');

            $prevision = LignePrevisionRecette::where('nomenclature_id', $nomenclatureId)
                ->whereIn('prevision_recette_id', $previsionRecetteIds)
                ->value('montant_prevu_initial');

            if ($prevision !== null) return (float) $prevision;
        }

        return 0.0;
    }

    /**
     * Réalisation RECETTE — montant réellement recouvré
     *
     * ⚠️ Utilise LignePrevisionRecette::montant_recouvre (déjà agrégé via
     *    calculerMontantRecouvre() depuis les recettes réelles mensuelles).
     */
    private function getRealisationRecette(int $nomenclatureId, int $annee, ?int $exerciceId): float
    {
        if (!$exerciceId) return 0.0;

        $realisation = PrevisionBudgetProgramme::where('nomenclature_id', $nomenclatureId)
            ->where('annee', $annee)
            ->where('type', 'realisation')
            ->where('categorie', 'recette')
            ->value('montant');

        if ($realisation !== null) return (float) $realisation;

        $previsionRecetteIds = PrevisionRecette::where('exercice_id', $exerciceId)->pluck('id');

        $realisation = LignePrevisionRecette::where('nomenclature_id', $nomenclatureId)
            ->whereIn('prevision_recette_id', $previsionRecetteIds)
            ->sum('montant_recouvre');

        return (float) ($realisation ?? 0);
    }

    private function calculerTotalGeneral(array $lignes, array $annees): array
    {
        $totaux = ['total_n1_n2' => 0, 'total_n_n1_n2' => 0];
        foreach ($annees as $key => $annee) {
            $totaux["prev_{$key}"] = array_sum(array_column($lignes, "prev_{$key}"));
            if (isset($lignes[0]["real_{$key}"])) {
                $totaux["real_{$key}"] = array_sum(array_column($lignes, "real_{$key}"));
            }
        }
        $totaux['total_n1_n2']   = array_sum(array_column($lignes, 'total_n1_n2'));
        $totaux['total_n_n1_n2'] = array_sum(array_column($lignes, 'total_n_n1_n2'));
        return $totaux;
    }
}
