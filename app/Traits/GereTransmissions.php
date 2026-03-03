<?php

namespace App\Traits;

use App\Models\Transmission;

trait GereTransmissions
{
    /**
     * Traiter automatiquement la transmission en attente pour l'utilisateur connecté
     */
    public function traiterMaTransmission(string $reponse = null): void
    {
        Transmission::where('document_type', get_class($this))
            ->where('document_id', $this->id)
            ->where('destinataire_id', auth()->id())
            ->where('statut', 'en_attente')
            ->first()
            ?->traiter($reponse);
    }

    /**
     * Rejeter automatiquement la transmission en attente pour l'utilisateur connecté
     */
    public function rejeterMaTransmission(string $motif): void
    {
        Transmission::where('document_type', get_class($this))
            ->where('document_id', $this->id)
            ->where('destinataire_id', auth()->id())
            ->where('statut', 'en_attente')
            ->first()
            ?->rejeter($motif);
    }

    /**
     * Traiter la transmission pour une action spécifique
     */
    public function traiterTransmissionPour(string $actionAttendue, string $reponse = null): void
    {
        Transmission::where('document_type', get_class($this))
            ->where('document_id', $this->id)
            ->where('destinataire_id', auth()->id())
            ->where('action_attendue', $actionAttendue)
            ->where('statut', 'en_attente')
            ->first()
            ?->traiter($reponse);
    }
}
