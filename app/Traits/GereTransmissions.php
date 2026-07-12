<?php

namespace App\Traits;

use App\Models\Transmission;

/**
 * ════════════════════════════════════════════════════════════
 * GereTransmissions — Apporte UNIQUEMENT les méthodes
 * de blocage et auto-clôture des transmissions.
 *
 * Les autres méthodes (transmissionEnCours, estDestinataire,
 * transmettreA, etc.) sont dans HasWorkflow.
 *
 * Ce trait est complémentaire à HasWorkflow.
 * ════════════════════════════════════════════════════════════
 */
trait GereTransmissions
{
    // =========================================================
    // ✅ MÉTHODE CENTRALE — Blocage avant toute action
    // =========================================================
    public function verifierPasEnTransmission(?string $actionEffectuee = null): void
    {
        $transmission = Transmission::where('document_id', $this->id)
            ->where('statut', 'en_attente')
            ->where(function ($q) {
                // ✅ Compatible morphMap — cherche FQCN ET alias
                $q->where('document_type', get_class($this))
                    ->orWhere('document_type', $this->_getMorphAlias());
            })
            ->with('destinataire', 'expediteur')
            ->latest('date_transmission')
            ->first();

        if (!$transmission) return; // Pas de transmission → OK

        $userId = auth()->id();

        // ── Destinataire ET action correspond à ce qui était attendu → OK
        if (
            $transmission->destinataire_id === $userId
            && $actionEffectuee
            && $this->_actionCorrespond($actionEffectuee, $transmission->action_attendue)
        ) {
            // Auto-clôture silencieuse
            $transmission->traiter("Action effectuée : {$actionEffectuee}");
            return;
        }

        // ── Destinataire mais action différente ───────────────
        if ($transmission->destinataire_id === $userId) {
            throw new \Exception(
                "🔒 Ce document vous a été transmis pour : {$transmission->getActionLabel()}.\n"
                    . "Clôturez ou retournez la transmission avant d'effectuer une autre action."
            );
        }

        // ── Expéditeur ────────────────────────────────────────
        if ($transmission->expediteur_id === $userId) {
            throw new \Exception(
                "🔒 Vous avez transmis ce document à {$transmission->destinataire?->name} "
                    . "pour : {$transmission->getActionLabel()}.\n"
                    . "Rappellez la transmission ou attendez sa clôture."
            );
        }

        // ── Tiers ─────────────────────────────────────────────
        throw new \Exception(
            "🔒 Ce document est en cours de transmission "
                . "(de {$transmission->expediteur?->name} "
                . "vers {$transmission->destinataire?->name}).\n"
                . "Attendez la clôture de la transmission."
        );
    }

    // =========================================================
    // ✅ Auto-clôture silencieuse quand l'action effectuée
    //    correspond à ce qui était attendu
    //
    // Appelez APRÈS avoir effectué l'action dans le modèle :
    //   $this->cloturerTransmissionSiActionMatch('valider');
    // =========================================================
    public function cloturerTransmissionSiActionMatch(
        string  $actionEffectuee,
        ?string $reponse = null
    ): void {
        $actionAttendue = $this->_getActionAttendue($actionEffectuee);
        if (!$actionAttendue) return;

        Transmission::where('document_id', $this->id)
            ->where('destinataire_id', auth()->id())
            ->where('action_attendue', $actionAttendue)
            ->where('statut', 'en_attente')
            ->where(function ($q) {
                $q->where('document_type', get_class($this))
                    ->orWhere('document_type', $this->_getMorphAlias());
            })
            ->first()
            ?->traiter($reponse ?? "Action effectuée : {$actionEffectuee}");
    }

    // ── Helpers privés ────────────────────────────────────────

    // ✅ Retourne l'alias morphMap ou le FQCN si pas d'alias défini
    private function _getMorphAlias(): string
    {
        $map = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        return array_search(get_class($this), $map) ?: get_class($this);
    }

    private function _getActionAttendue(string $actionEffectuee): ?string
    {
        return match ($actionEffectuee) {
            'valider', 'validation'   => 'validation',
            'engager', 'engagement'   => 'engagement',
            'signer', 'signature'     => 'signature',
            'viser', 'verification'   => 'verification',
            'liquider', 'liquidation' => 'liquidation',
            'payer', 'paiement'       => 'paiement',
            'corriger', 'correction'  => 'correction',
            default                     => null,
        };
    }

    private function _actionCorrespond(string $actionEffectuee, string $actionAttendue): bool
    {
        return $this->_getActionAttendue($actionEffectuee) === $actionAttendue;
    }
}
