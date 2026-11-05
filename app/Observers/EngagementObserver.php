<?php

namespace App\Observers;

use App\Models\Engagement;
use App\Services\Budget\HistoriqueLigneBudgetaireService;

/**
 * Le gel à l'émission : copier l'état de la ligne au moment où le document naît.
 *
 * La coupure chronologique (volet 1) reconstruit l'état du jour du papier à partir de ce
 * que la base en dit encore. Elle ne peut pas deviner un engagement que
 * Engagement::annuler() a effacé en forceDelete(), ni un virement re-daté quatre mois
 * après le visa. Ce gel-là est la copie du papier lui-même : il ne se recalcule plus.
 *
 * Deux moments, pas un de plus :
 *  - `created()` : l'identifiant vient d'être connu, et « engagé avant » se trie sur
 *    (date, identifiant) en s'excluant soi-même. À `creating()`, le document n'aurait
 *    pas de place dans ce tri.
 *  - `updating()` : l'état se reprend quand le document change de consistance — budget,
 *    ligne, jour, montant. Rien d'autre ne le défait : poser un visa, solder, corriger
 *    une observation ne doit pas faire bouger un chiffre déjà signé.
 *
 * Le gel s'écrit sans événements ni journal d'activité : ce n'est pas une décision de
 * gestion, c'est la trace de l'état. `snapshot_fige_le` dit laquelle des deux.
 */
class EngagementObserver
{
    public function created(Engagement $engagement): void
    {
        app(HistoriqueLigneBudgetaireService::class)->geler($engagement);
    }

    public function updating(Engagement $engagement): void
    {
        $historique = app(HistoriqueLigneBudgetaireService::class);

        $gele = $historique->estGele($engagement);
        $defait = $engagement->isDirty(HistoriqueLigneBudgetaireService::CHAMPS_QUI_GELENT);

        if (!$gele || $defait) {
            // Rempli ici, écrit par l'UPDATE en cours : `updating()` est déclenché avant
            // que le modèle ne calcule ses colonnes sales.
            $historique->poserGel($engagement);
        }
    }
}
