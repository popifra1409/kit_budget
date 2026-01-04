<?php

namespace App\Services;

use App\Models\Exercice;
use App\Models\BordereauEngagement;
use App\Models\Engagement;
use App\Models\Budget;
use App\Models\Tache;
use Illuminate\Support\Collection;

class ValidationExerciceService
{
    /**
     * Valider si un exercice peut être clôturé
     */
    public function validerCloture(Exercice $exercice): array
    {
        $erreurs = [];
        $avertissements = [];
        $infos = [];

        // 1. Vérifier que l'exercice est actif
        if (!$exercice->estActif()) {
            $erreurs[] = "L'exercice doit être actif pour être clôturé (statut actuel : {$exercice->statut})";
            return [
                'valid' => false,
                'erreurs' => $erreurs,
                'avertissements' => $avertissements,
                'infos' => $infos,
            ];
        }

        // 2. Vérifier les bordereaux en brouillon
        $bordereauxBrouillon = BordereauEngagement::where('exercice_id', $exercice->id)
            ->where('statut', 'brouillon')
            ->count();

        if ($bordereauxBrouillon > 0) {
            $erreurs[] = "{$bordereauxBrouillon} bordereau(x) d'engagement en brouillon. Ils doivent être validés ou supprimés.";
        }

        // 3. Vérifier les engagements par statut
        $engagementsTotal = Engagement::where('exercice_id', $exercice->id)->count();
        $engagementsValides = Engagement::where('exercice_id', $exercice->id)
            ->where('statut', 'valide')
            ->count();
        $engagementsBrouillon = Engagement::where('exercice_id', $exercice->id)
            ->where('statut', 'brouillon')
            ->count();

        if ($engagementsBrouillon > 0) {
            $avertissements[] = "{$engagementsBrouillon} engagement(s) en brouillon. Validez-les avant clôture.";
        }

        if ($engagementsTotal > 0) {
            $infos[] = "Engagements : {$engagementsTotal} (dont {$engagementsValides} validé(s))";
        }

        // Montant total engagé
        $montantTotalEngage = Engagement::where('exercice_id', $exercice->id)
            ->sum('montant_engage');
        $infos[] = "Montant total engagé : " . number_format($montantTotalEngage, 0, ',', ' ') . " FCFA";

        // 4. Vérifier que tous les budgets ont un exercice
        $budgetsSansExercice = Budget::whereNull('exercice_id')->count();
        if ($budgetsSansExercice > 0) {
            $avertissements[] = "{$budgetsSansExercice} budget(s) sans exercice. Ils ne seront pas pris en compte.";
        }

        // 5. Vérifier que toutes les tâches ont un exercice
        $tachesSansExercice = Tache::whereNull('exercice_id')->count();
        if ($tachesSansExercice > 0) {
            $avertissements[] = "{$tachesSansExercice} tâche(s) sans exercice. Elles ne seront pas prises en compte.";
        }

        // 6. Statistiques de l'exercice
        $stats = $exercice->calculerStatistiques();
        $infos[] = "Programmes : {$stats['nb_programmes']}";
        $infos[] = "Budgets : {$stats['nb_budgets']}";
        $infos[] = "Bordereaux : {$stats['nb_bordereaux']}";
        $infos[] = "Montant total AE : " . number_format($stats['montant_total_ae'], 0, ',', ' ') . " FCFA";
        $infos[] = "Montant total CP : " . number_format($stats['montant_total_cp'], 0, ',', ' ') . " FCFA";

        // 7. Vérifier l'équilibre budgétaire
        $budgetTotal = Budget::where('exercice_id', $exercice->id)
            ->with('lignesBudgetaires')
            ->get()
            ->sum(function ($budget) {
                return $budget->lignesBudgetaires->sum('budget_rectifie');
            });

        $engageTotal = Budget::where('exercice_id', $exercice->id)
            ->with('lignesBudgetaires')
            ->get()
            ->sum(function ($budget) {
                return $budget->lignesBudgetaires->sum('engage');
            });

        $tauxExecution = $budgetTotal > 0 ? ($engageTotal / $budgetTotal) * 100 : 0;
        $infos[] = "Taux d'exécution : " . number_format($tauxExecution, 2) . "%";

        if ($tauxExecution < 50) {
            $avertissements[] = "Taux d'exécution faible ({$tauxExecution}%). Vérifiez si normal.";
        }

        return [
            'valid' => count($erreurs) === 0,
            'erreurs' => $erreurs,
            'avertissements' => $avertissements,
            'infos' => $infos,
            'stats' => [
                'budget_total' => $budgetTotal,
                'engage_total' => $engageTotal,
                'disponible' => $budgetTotal - $engageTotal,
                'taux_execution' => $tauxExecution,
            ],
        ];
    }

    /**
     * Obtenir un résumé pour affichage
     */
    public function getResumeCloture(Exercice $exercice): string
    {
        $validation = $this->validerCloture($exercice);

        $resume = "=== VALIDATION DE CLÔTURE - EXERCICE {$exercice->annee} ===\n\n";

        if (!$validation['valid']) {
            $resume .= "❌ ERREURS BLOQUANTES :\n";
            foreach ($validation['erreurs'] as $erreur) {
                $resume .= "  • {$erreur}\n";
            }
            $resume .= "\n";
        }

        if (!empty($validation['avertissements'])) {
            $resume .= "⚠️  AVERTISSEMENTS :\n";
            foreach ($validation['avertissements'] as $avertissement) {
                $resume .= "  • {$avertissement}\n";
            }
            $resume .= "\n";
        }

        $resume .= "📊 INFORMATIONS :\n";
        foreach ($validation['infos'] as $info) {
            $resume .= "  • {$info}\n";
        }

        if ($validation['valid']) {
            $resume .= "\n✅ L'exercice peut être clôturé.\n";
        } else {
            $resume .= "\n❌ Corrigez les erreurs avant de clôturer.\n";
        }

        return $resume;
    }
}
