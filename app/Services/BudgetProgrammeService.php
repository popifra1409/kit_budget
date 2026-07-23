<?php

namespace App\Services;

use App\Models\Exercice;
use App\Models\Engagement;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionBudgetProgramme;
use Illuminate\Support\Collection;

/**
 * ══════════════════════════════════════════════════════════
 * Service — Budget Programme Triennal
 *
 * Génère le tableau des dépenses de fonctionnement sur
 * le plan triennal (N-2, N-1, N, N+1, N+2)
 * ══════════════════════════════════════════════════════════
 */
class BudgetProgrammeService
{
    /**
     * Collecter toutes les données pour l'export
     *
     * @param int $anneeRef  Année de référence (ex: 2026)
     * @param string $categorie 'fonctionnement' ou 'investissement'
     */
    public function collecterDonnees(int $anneeRef, string $categorie = 'fonctionnement'): array
    {
        $annees = [
            'n_2'  => $anneeRef - 2,  // Ex: 2024
            'n_1'  => $anneeRef - 1,  // Ex: 2025
            'n'    => $anneeRef,       // Ex: 2026
            'n1'   => $anneeRef + 1,  // Ex: 2027
            'n2'   => $anneeRef + 2,  // Ex: 2028
        ];

        // ── Exercices concernés ──────────────────────────────
        $exercices = Exercice::whereIn('annee', array_values($annees))->get()->keyBy('annee');

        // ── Nomenclatures de fonctionnement ─────────────────
        $nomenclatures = NomenclatureBudgetaire::query()
            ->where('actif', true)
            ->with('groupe')
            ->when(
                $categorie === 'fonctionnement',
                fn($q) => $q->where(function ($q) {
                    // Codes commençant par 6 (charges) pour fonctionnement
                    $q->where('code', 'like', '6%');
                })
            )
            ->orderBy('code')
            ->get();

        // ── Regroupement par chapitre ─────────────────────────
        $chapitres = $nomenclatures->groupBy(fn($n) => substr($n->code, 0, 3));

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

            // Pour chaque année
            foreach ($annees as $key => $annee) {
                $exercice = $exercices[$annee] ?? null;

                // ── Prévision ────────────────────────────────
                $prevision = $this->getPrevision($nomenclature->id, $annee, $exercice?->id);
                $ligne["prev_{$key}"] = $prevision;

                // ── Réalisation ──────────────────────────────
                if ($annee <= $anneeRef) {
                    $realisation = $this->getRealisation($nomenclature->id, $annee, $exercice?->id);
                    $ligne["real_{$key}"] = $realisation;

                    // Taux d'exécution
                    $ligne["taux_{$key}"] = $prevision > 0
                        ? round($realisation / $prevision, 4)
                        : null;
                }
            }

            // ── Totaux triennaux ─────────────────────────────
            $ligne['total_n1_n2']   = ($ligne['prev_n1'] ?? 0) + ($ligne['prev_n2'] ?? 0);
            $ligne['total_n_n1_n2'] = ($ligne['prev_n'] ?? 0) + ($ligne['prev_n1'] ?? 0) + ($ligne['prev_n2'] ?? 0);

            $lignes[] = $ligne;
        }

        // ── Tri : groupe (ordre) puis code (pour garder le regroupement chapitre cohérent) ──
        usort($lignes, function ($a, $b) {
            return [$a['groupe_ordre'], $a['imputation']] <=> [$b['groupe_ordre'], $b['imputation']];
        });

        return [
            'annees'          => $annees,
            'annee_reference' => $anneeRef,
            'categorie'       => $categorie,
            'lignes'          => $lignes,
            'chapitres'       => $this->collecterChapitres($lignes),
            'total_general'   => $this->calculerTotalGeneral($lignes, $annees),
        ];
    }

    /**
     * Récupérer la prévision (depuis PrevisionBudgetProgramme ou LigneBudgetaire)
     */
    private function getPrevision(int $nomenclatureId, int $annee, ?int $exerciceId): float
    {
        // 1. Chercher dans PrevisionBudgetProgramme (saisie manuelle ou calculée)
        $prevision = PrevisionBudgetProgramme::where('nomenclature_id', $nomenclatureId)
            ->where('annee', $annee)
            ->where('type', 'prevision')
            ->value('montant');

        if ($prevision !== null) return (float) $prevision;

        // 2. Fallback : chercher dans LigneBudgetaire si exercice existe
        if ($exerciceId) {
            $prevision = \App\Models\LigneBudgetaire::where('nomenclature_id', $nomenclatureId)
                ->where('budget_id', function ($q) use ($exerciceId) {
                    $q->select('id')->from('budgets')
                        ->where('exercice_id', $exerciceId)->limit(1);
                })
                ->value('budget_initial'); // ✅ colonne réelle dans lignes_budgetaires

            if ($prevision !== null) return (float) $prevision;
        }

        return 0.0;
    }

    /**
     * Récupérer la réalisation depuis les engagements/dépenses réels
     */
    private function getRealisation(int $nomenclatureId, int $annee, ?int $exerciceId): float
    {
        if (!$exerciceId) return 0.0;

        // 1. Chercher dans PrevisionBudgetProgramme (si saisie manuelle)
        $realisation = PrevisionBudgetProgramme::where('nomenclature_id', $nomenclatureId)
            ->where('annee', $annee)
            ->where('type', 'realisation')
            ->value('montant');

        if ($realisation !== null) return (float) $realisation;

        // 2. Calculer depuis les engagements réels de l'exercice
        $realisation = Engagement::where('exercice_id', $exerciceId)
            ->where('nomenclature_principale_id', $nomenclatureId)
            ->where('statut', 'definitif')
            ->sum('montant_engage');

        return (float) ($realisation ?? 0);
    }

    private function collecterChapitres(array $lignes): array
    {
        $chapitres = [];
        foreach ($lignes as $ligne) {
            $code = substr($ligne['imputation'], 0, 3);
            if (!isset($chapitres[$code])) {
                $chapitres[$code] = ['code' => $code, 'lignes' => []];
            }
            $chapitres[$code]['lignes'][] = $ligne;
        }
        return $chapitres;
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
