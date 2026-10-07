<?php

namespace App\Observers;

use App\Models\Engagement;
use App\Models\Liquidation;
use App\Models\OrdonnancePaiement;
use App\Services\Budget\PeriodeExecutionService;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrôle des périodes d'exécution, en un seul point, pour tous les écrans :
 *  - création d'un engagement          → « engagement » ;
 *  - création / liquidation             → « liquidation » ;
 *  - création d'une OP                  → « ordonnancement » ;
 *  - passage d'une OP au statut « payée » → « paiement ».
 * Enregistré sur Engagement, Liquidation et OrdonnancePaiement (AppServiceProvider).
 */
class PeriodeExecutionObserver
{
    public function creating(Model $modele): void
    {
        $service = app(PeriodeExecutionService::class);

        match (true) {
            $modele instanceof Engagement         => $service->verifier($modele->exercice_id, 'engagement'),
            $modele instanceof Liquidation        => $service->verifier($this->exerciceDeLEngagement($modele->engagement_id) ?? $modele->exercice_id, 'liquidation'),
            $modele instanceof OrdonnancePaiement => $service->verifier($this->exerciceDeLEngagement($modele->engagement_id), 'ordonnancement'),
            default                               => null,
        };
    }

    public function updating(Model $modele): void
    {
        $service = app(PeriodeExecutionService::class);

        if ($modele instanceof OrdonnancePaiement && $modele->isDirty('statut') && $modele->statut === 'payee') {
            $service->verifier($this->exerciceDeLEngagement($modele->engagement_id), 'paiement');
        }

        if ($modele instanceof Liquidation && $modele->isDirty('statut') && $modele->statut === 'liquidee') {
            $service->verifier($this->exerciceDeLEngagement($modele->engagement_id) ?? $modele->exercice_id, 'liquidation');
        }
    }

    /** Exercice d'une dépense = exercice de son engagement (et non l'exercice actif). */
    protected function exerciceDeLEngagement(?int $engagementId): ?int
    {
        return $engagementId
            ? Engagement::withoutGlobalScope('exercice')->whereKey($engagementId)->value('exercice_id')
            : null;
    }
}
