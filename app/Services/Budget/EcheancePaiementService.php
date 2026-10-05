<?php

namespace App\Services\Budget;

use App\Models\OrdonnancePaiement;
use App\Services\ParametresExecution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Compte à rebours de paiement après liquidation, alertes et arriérés.
 * Seuils d'alerte et taux des intérêts moratoires : paramètres d'exécution EN VIGUEUR AUJOURD'HUI.
 * L'échéance elle-même est figée sur l'OP (copiée de la liquidation).
 */
class EcheancePaiementService
{
    public const ETATS = [
        'dans_delai'   => ['libelle' => 'Dans les délais',     'couleur' => 'success'],
        'alerte'       => ['libelle' => 'Alerte',              'couleur' => 'warning'],
        'alerte_forte' => ['libelle' => 'Échéance proche',     'couleur' => 'danger'],
        'arriere'      => ['libelle' => 'Arriéré',             'couleur' => 'danger'],
        'payee'        => ['libelle' => 'Payée dans les délais', 'couleur' => 'gray'],
        'payee_retard' => ['libelle' => 'Payée en retard',     'couleur' => 'warning'],
    ];

    /**
     * État de l'échéance d'une OP, ou null si l'OP n'a pas d'échéance (OP sans liquidation).
     * @return array{etat: string, libelle: string, couleur: string, jours: int, texte: string, interets: float}|null
     */
    public function etat(OrdonnancePaiement $op): ?array
    {
        if (blank($op->date_echeance_paiement)) {
            return null;
        }

        $echeance = Carbon::parse($op->date_echeance_paiement)->startOfDay();

        // OP payée : dans les délais ou en retard
        if ($op->statut === 'payee') {
            $paiement = $op->date_paiement ? Carbon::parse($op->date_paiement)->startOfDay() : today();
            $retard = max(0, (int) $echeance->diffInDays($paiement, false));
            $etat = $retard > 0 ? 'payee_retard' : 'payee';

            return $this->resultat($etat, -$retard, $retard > 0 ? "Payée avec {$retard} j de retard" : 'Payée dans les délais', $op, $retard);
        }

        $jours = (int) today()->diffInDays($echeance, false);   // négatif = échéance dépassée

        if ($jours < 0) {
            $retard = abs($jours);
            return $this->resultat('arriere', $jours, "Arriéré de {$retard} j", $op, $retard);
        }

        $etat = match (true) {
            $jours <= (int) ParametresExecution::get('alerte_paiement_2_jours') => 'alerte_forte',
            $jours <= (int) ParametresExecution::get('alerte_paiement_1_jours') => 'alerte',
            default                                                           => 'dans_delai',
        };

        return $this->resultat($etat, $jours, "J-{$jours}", $op, 0);
    }

    /** Intérêts moratoires estimés : montant × taux annuel × jours de retard / 365 (0 si taux nul). */
    public function interetsMoratoires(float $montant, int $joursRetard): float
    {
        $taux = (float) ParametresExecution::get('taux_interets_moratoires');

        return $taux > 0 && $joursRetard > 0
            ? round($montant * $taux / 100 * $joursRetard / 365, 0)
            : 0.0;
    }

    /** Filtre de requête sur l'état d'échéance (OP non payées). */
    public function filtrer(Builder $query, string $etat): Builder
    {
        $alerte1 = (int) ParametresExecution::get('alerte_paiement_1_jours');
        $alerte2 = (int) ParametresExecution::get('alerte_paiement_2_jours');
        $aujourdhui = today()->toDateString();

        $query->whereNotNull('date_echeance_paiement')->where('statut', '!=', 'payee');

        return match ($etat) {
            'arriere'      => $query->whereDate('date_echeance_paiement', '<', $aujourdhui),
            'alerte_forte' => $query->whereBetween('date_echeance_paiement', [$aujourdhui, today()->addDays($alerte2)->toDateString()]),
            'alerte'       => $query->whereBetween('date_echeance_paiement', [today()->addDays($alerte2 + 1)->toDateString(), today()->addDays($alerte1)->toDateString()]),
            'dans_delai'   => $query->whereDate('date_echeance_paiement', '>', today()->addDays($alerte1)->toDateString()),
            default        => $query,
        };
    }

    /** Synthèse pour le tableau de bord (OP standard non payées ayant une échéance). */
    public function synthese(): array
    {
        $ops = OrdonnancePaiement::query()
            ->where('type_ordonnance', 'standard')
            ->where('statut', '!=', 'payee')
            ->where('statut', '!=', 'annulee')
            ->whereNotNull('date_echeance_paiement')
            ->get();

        $etats = $ops->map(fn($op) => ['op' => $op, 'e' => $this->etat($op)]);
        $arrieres = $etats->where('e.etat', 'arriere');

        return [
            'arrieres_nombre'   => $arrieres->count(),
            'arrieres_montant'  => (float) $arrieres->sum(fn($x) => $x['op']->montant_net),
            'interets_estimes'  => (float) $arrieres->sum(fn($x) => $x['e']['interets']),
            'alertes_nombre'    => $etats->whereIn('e.etat', ['alerte', 'alerte_forte'])->count(),
            'alertes_montant'   => (float) $etats->whereIn('e.etat', ['alerte', 'alerte_forte'])->sum(fn($x) => $x['op']->montant_net),
            'dans_delai_nombre' => $etats->where('e.etat', 'dans_delai')->count(),
        ];
    }

    protected function resultat(string $etat, int $jours, string $texte, OrdonnancePaiement $op, int $retard): array
    {
        return [
            'etat'     => $etat,
            'libelle'  => self::ETATS[$etat]['libelle'],
            'couleur'  => self::ETATS[$etat]['couleur'],
            'jours'    => $jours,
            'texte'    => $texte,
            'interets' => $this->interetsMoratoires((float) $op->montant_net, $retard),
        ];
    }
}
