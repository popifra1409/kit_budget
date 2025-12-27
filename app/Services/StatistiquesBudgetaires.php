<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Engagement;
use App\Models\BonCommande;
use App\Models\DecisionAdministrative;
use App\Models\LigneBudgetaire;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class StatistiquesBudgetaires
{
    /**
     * Obtenir les statistiques du budget pour l'exercice courant
     */
    public static function getVueEnsemble(?int $exercice = null): array
    {
        $exercice = $exercice ?? now()->year;

        // Budget actif pour l'exercice
        $budget = Budget::where('exercice', $exercice)
            ->where('actif', true)
            ->first();

        if (!$budget) {
            return [
                'budget_initial' => 0,
                'virements' => 0,
                'budget_actualise' => 0,
                'engage' => 0,
                'disponible' => 0,
                'taux_execution' => 0,
                'exercice' => $exercice,
            ];
        }

        // Calculer les virements (total ajustements)
        $virements = $budget->lignesBudgetaires()
            ->sum(DB::raw('montant_vote - montant_initial'));

        // Totaux
        $budgetInitial = $budget->lignesBudgetaires()->sum('montant_initial');
        $budgetActualise = $budget->lignesBudgetaires()->sum('montant_vote');
        $engage = $budget->lignesBudgetaires()->sum('engage');
        $disponible = $budgetActualise - $engage;

        $tauxExecution = $budgetActualise > 0
            ? round(($engage / $budgetActualise) * 100, 2)
            : 0;

        return [
            'budget_initial' => $budgetInitial,
            'virements' => $virements,
            'budget_actualise' => $budgetActualise,
            'engage' => $engage,
            'disponible' => $disponible,
            'taux_execution' => $tauxExecution,
            'exercice' => $exercice,
            'budget' => $budget,
        ];
    }

    /**
     * Obtenir les engagements par type
     */
    public static function getEngagementsParType(?int $exercice = null): array
    {
        $exercice = $exercice ?? now()->year;

        $stats = Engagement::where('exercice', $exercice)
            ->where('statut', '!=', 'annule')
            ->select('type_engagement', DB::raw('COUNT(*) as nombre'), DB::raw('SUM(montant_engage) as total'))
            ->groupBy('type_engagement')
            ->get();

        $total = $stats->sum('total');

        return $stats->map(function ($stat) use ($total) {
            return [
                'type' => $stat->type_engagement,
                'nombre' => $stat->nombre,
                'montant' => $stat->total,
                'pourcentage' => $total > 0 ? round(($stat->total / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    /**
     * Obtenir les top nomenclatures budgétaires les plus consommées
     */
    public static function getTopServices(int $limit = 5, ?int $exercice = null): array
    {
        $exercice = $exercice ?? now()->year;

        // Récupérer le budget actif
        $budget = Budget::where('exercice', $exercice)
            ->where('actif', true)
            ->first();

        if (!$budget) {
            return [];
        }

        // Top nomenclatures par montant engagé
        $stats = LigneBudgetaire::where('budget_id', $budget->id)
            ->with('nomenclature')
            ->where('engage', '>', 0)
            ->orderBy('engage', 'desc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($stats as $stat) {
            if ($stat->nomenclature) {
                $result[] = [
                    'service' => $stat->nomenclature->code . ' - ' . $stat->nomenclature->libelle,
                    'montant' => $stat->engage,
                ];
            }
        }

        return $result;
    }

    /**
     * Obtenir l'évolution mensuelle des engagements
     */
    public static function getEvolutionMensuelle(?int $exercice = null): array
    {
        $exercice = $exercice ?? now()->year;

        $stats = Engagement::where('exercice', $exercice)
            ->where('statut', '!=', 'annule')
            ->select(
                DB::raw('EXTRACT(MONTH FROM date_engagement) as mois'),
                DB::raw('SUM(montant_engage) as total')
            )
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        // Créer un tableau avec tous les mois (1-12)
        $mois = [
            1 => 'Jan',
            2 => 'Fév',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aoû',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Déc'
        ];

        $result = [];
        foreach ($mois as $numero => $nom) {
            $stat = $stats->firstWhere('mois', $numero);
            $result[] = [
                'mois' => $nom,
                'montant' => $stat ? $stat->total : 0,
            ];
        }

        return $result;
    }

    /**
     * Obtenir les alertes
     */
    public static function getAlertes(?int $exercice = null): array
    {
        $exercice = $exercice ?? now()->year;

        $alertes = [];

        // Budget actif
        $budget = Budget::where('exercice', $exercice)
            ->where('actif', true)
            ->first();

        if (!$budget) {
            return [];
        }

        // 1. Lignes budgétaires > 90% consommées
        $lignesSaturees = LigneBudgetaire::where('budget_id', $budget->id)
            ->whereRaw('montant_vote > 0')
            ->whereRaw('(engage / montant_vote) > 0.9')
            ->count();

        if ($lignesSaturees > 0) {
            $alertes[] = [
                'type' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
                'message' => "{$lignesSaturees} ligne(s) budgétaire(s) > 90% consommée(s)",
                'count' => $lignesSaturees,
            ];
        }

        // 2. BC en attente de validation
        $bcEnAttente = BonCommande::where('exercice', $exercice)
            ->where('statut', 'brouillon')
            ->count();

        if ($bcEnAttente > 0) {
            $alertes[] = [
                'type' => 'info',
                'icon' => 'heroicon-o-clock',
                'message' => "{$bcEnAttente} BC en attente de validation",
                'count' => $bcEnAttente,
            ];
        }

        // 3. DA expirées (non engagées après 30 jours)
        $daExpirees = DecisionAdministrative::where('exercice', $exercice)
            ->where('statut', 'valide')
            ->where('engage', false)
            ->where('date_validation', '<', now()->subDays(30))
            ->count();

        if ($daExpirees > 0) {
            $alertes[] = [
                'type' => 'danger',
                'icon' => 'heroicon-o-exclamation-circle',
                'message' => "{$daExpirees} DA expirée(s) (> 30 jours)",
                'count' => $daExpirees,
            ];
        }

        // 4. Engagements en attente
        $engagementsEnAttente = Engagement::where('exercice', $exercice)
            ->where('statut', 'provisoire')
            ->count();

        if ($engagementsEnAttente > 0) {
            $alertes[] = [
                'type' => 'info',
                'icon' => 'heroicon-o-document-text',
                'message' => "{$engagementsEnAttente} engagement(s) provisoire(s)",
                'count' => $engagementsEnAttente,
            ];
        }

        return $alertes;
    }

    /**
     * Formater un montant en FCFA
     */
    public static function formatMontant(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Formater un pourcentage
     */
    public static function formatPourcentage(float $pourcentage): string
    {
        return number_format($pourcentage, 1) . '%';
    }
}
