<?php

namespace App\Services\Budget;

use App\Models\Exercice;
use App\Services\ParametresExecution;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * Périodes d'exécution d'un exercice N :
 *  - gestion              : jusqu'au 31/12/N        → toutes opérations ;
 *  - période complémentaire : du 01/01/N+1 au terme paramétré → plus d'engagement sur N, mais
 *                           liquidation, ordonnancement et paiement des dépenses engagées en N ;
 *  - terminée             : au-delà                  → plus aucune opération sur N.
 * Dérogation : permission « operer_hors_periode_execution ».
 */
class PeriodeExecutionService
{
    public const OPERATIONS = [
        'engagement'     => "l'engagement",
        'liquidation'    => 'la liquidation',
        'ordonnancement' => "l'ordonnancement",
        'paiement'       => 'le paiement',
    ];

    public function finGestion(Exercice $exercice): Carbon
    {
        return Carbon::create($exercice->annee, 12, 31)->endOfDay();
    }

    /** Fin de la période complémentaire (durée en vigueur au 31/12/N). */
    public function finComplementaire(Exercice $exercice): Carbon
    {
        $jours = (int) ParametresExecution::get('periode_complementaire_jours', Carbon::create($exercice->annee, 12, 31));

        return Carbon::create($exercice->annee, 12, 31)->addDays($jours)->endOfDay();
    }

    /** @return array{phase: string, libelle: string, fin_gestion: Carbon, fin_complementaire: Carbon, jours_restants: ?int} */
    public function situation(Exercice $exercice): array
    {
        $finGestion = $this->finGestion($exercice);
        $finComplementaire = $this->finComplementaire($exercice);
        $maintenant = now();

        [$phase, $libelle, $echeance] = match (true) {
            $maintenant->lte($finGestion)        => ['gestion', 'Gestion en cours', $finGestion],
            $maintenant->lte($finComplementaire) => ['complementaire', 'Période complémentaire', $finComplementaire],
            default                              => ['terminee', 'Période complémentaire terminée', null],
        };

        return [
            'phase'              => $phase,
            'libelle'            => $libelle,
            'fin_gestion'        => $finGestion,
            'fin_complementaire' => $finComplementaire,
            'jours_restants'     => $echeance ? (int) today()->diffInDays($echeance->copy()->startOfDay(), false) : null,
        ];
    }

    /**
     * Refuse une opération hors de sa période. Sans effet hors d'une session utilisateur
     * (commandes, tâches planifiées) ou avec la permission de dérogation.
     */
    public function verifier(?int $exerciceId, string $operation): void
    {
        if (!$exerciceId || !auth()->check() || auth()->user()->can('operer_hors_periode_execution')) {
            return;
        }

        $exercice = Exercice::find($exerciceId);
        if (!$exercice) {
            return;
        }

        $s = $this->situation($exercice);
        $libelle = self::OPERATIONS[$operation] ?? $operation;
        $fin = $s['fin_complementaire']->format('d/m/Y');

        if ($operation === 'engagement' && $s['phase'] !== 'gestion') {
            $this->refuser(
                "Engagement impossible sur l'exercice {$exercice->annee} : la gestion est close depuis le 31/12/{$exercice->annee}. "
                    . ($s['phase'] === 'complementaire'
                        ? "Pendant la période complémentaire (jusqu'au {$fin}), seules la liquidation, l'ordonnancement et le paiement des dépenses engagées en {$exercice->annee} sont permis."
                        : "Engagez la dépense sur l'exercice en cours.")
            );
        }

        if ($s['phase'] === 'terminee') {
            $this->refuser(
                "Opération impossible ({$libelle}) sur l'exercice {$exercice->annee} : la période complémentaire s'est terminée le {$fin}. "
                    . "La dépense relève désormais des reports, ou d'une régularisation exceptionnelle autorisée."
            );
        }
    }

    /**
     * Refus : dans un écran Filament, notification claire puis arrêt propre de l'action (Halt) ;
     * ailleurs (code, API), exception métier.
     */
    protected function refuser(string $message): void
    {
        if (class_exists(\Livewire\Livewire::class) && \Livewire\Livewire::isLivewireRequest()) {
            \Filament\Notifications\Notification::make()
                ->title('Opération hors période d\'exécution')
                ->body($message)
                ->warning()
                ->persistent()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        throw new DomainException($message);
    }
}
