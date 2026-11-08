<?php

namespace App\Observers;

use App\Models\OrdonnancePaiement;
use App\Services\Budget\GelOrdonnancePaiementService;

/**
 * Le gel à l'émission : copier ce que le papier de l'ordonnance imprime, au moment où
 * l'ordonnance naît.
 *
 * L'OP se crée déjà « emise » (Engagement::creerOrdonnancesPaiement) : il n'y a pas de
 * brouillon dont il faudrait attendre la signature. Le gel se pose donc à `created()`.
 *
 * `updating()` ne reprend l'état que si le document change de consistance — engagement,
 * type, jour d'émission, montants, bénéficiaire. Marquer payée, corriger une observation,
 * poser un mode de paiement ne doit pas faire bouger un chiffre déjà imprimé : c'est
 * précisément ce que `ChangementBeneficiaireService` écrase aujourd'hui en `updateQuietly`
 * sur les OP non payées, et que le gel devra suivre plutôt que subir.
 *
 * Le gel s'écrit sans événements ni journal d'activité : ce n'est pas une décision de
 * gestion, c'est la trace de l'état. `snapshot_fige_le` dit laquelle des deux.
 */
class OrdonnancePaiementObserver
{
    public function created(OrdonnancePaiement $op): void
    {
        app(GelOrdonnancePaiementService::class)->geler($op);
    }

    public function updating(OrdonnancePaiement $op): void
    {
        $gel = app(GelOrdonnancePaiementService::class);

        if (!$gel->estGele($op) || $op->isDirty(GelOrdonnancePaiementService::CHAMPS_QUI_GELENT)) {
            // Rempli ici, écrit par l'UPDATE en cours : `updating()` est déclenché avant
            // que le modèle ne calcule ses colonnes sales.
            $gel->poserGel($op);
        }
    }
}
