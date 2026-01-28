<?php

namespace App\Traits;

use App\Models\Transmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWorkflow
{
    /**
     * Relation polymorphique : Transmissions
     */
    public function transmissions(): MorphMany
    {
        return $this->morphMany(Transmission::class, 'document');
    }

    /**
     * Obtenir la transmission en cours (en attente)
     */
    public function transmissionEnCours()
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest('date_transmission')
            ->first();
    }

    /**
     * Obtenir toutes les transmissions en attente
     */
    public function transmissionsEnAttente()
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->orderBy('date_transmission', 'desc')
            ->get();
    }

    /**
     * Obtenir l'historique complet des transmissions
     */
    public function historiqueTransmissions()
    {
        return $this->transmissions()
            ->with(['expediteur', 'destinataire'])
            ->orderBy('date_transmission', 'desc')
            ->get();
    }

    /**
     * Transmettre le document à un utilisateur
     */
    public function transmettreA(
        User $destinataire,
        string $actionAtttendue,
        string $commentaire = null,
        array $options = []
    ): Transmission {
        $transmission = $this->transmissions()->create([
            'expediteur_id' => auth()->id(),
            'destinataire_id' => $destinataire->id,
            'action_attendue' => $actionAtttendue,
            'statut' => 'en_attente',
            'commentaire' => $commentaire,
            'priorite' => $options['priorite'] ?? 'normale',
            'date_limite' => $options['date_limite'] ?? null,
            'date_transmission' => now(),
            'documents_joints' => $options['documents_joints'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);

        // Envoyer notification
        $destinataire->notify(new \App\Notifications\NouvelleTransmissionNotification($transmission));

        return $transmission;
    }

    /**
     * Vérifier si le document peut être transmis
     */
    public function peutEtreTransmis(): bool
    {
        // Pas de transmission en cours
        $transmissionEnCours = $this->transmissionEnCours();

        return $transmissionEnCours === null;
    }

    /**
     * Vérifier si l'utilisateur est le destinataire actuel
     */
    public function estDestinataireActuel(User $user = null): bool
    {
        $user = $user ?? auth()->user();

        $transmissionEnCours = $this->transmissionEnCours();

        if (!$transmissionEnCours) {
            return false;
        }

        return $transmissionEnCours->destinataire_id === $user->id;
    }

    /**
     * Obtenir le destinataire actuel
     */
    public function getDestinataireActuel(): ?User
    {
        $transmissionEnCours = $this->transmissionEnCours();

        return $transmissionEnCours?->destinataire;
    }

    /**
     * Obtenir le statut du workflow
     */
    public function getStatutWorkflow(): string
    {
        $transmissionEnCours = $this->transmissionEnCours();

        if (!$transmissionEnCours) {
            return 'Aucune transmission en cours';
        }

        return "Chez {$transmissionEnCours->destinataire->name} - {$transmissionEnCours->getActionLabel()}";
    }

    /**
     * Retourner le document à l'expéditeur pour correction
     */
    public function retournerPourCorrection(string $motif): Transmission
    {
        $transmissionEnCours = $this->transmissionEnCours();

        if (!$transmissionEnCours) {
            throw new \Exception("Aucune transmission en cours");
        }

        // Marquer la transmission actuelle comme rejetée
        $transmissionEnCours->rejeter($motif);

        // Créer une nouvelle transmission vers l'expéditeur original
        return $this->transmettreA(
            $transmissionEnCours->expediteur,
            'correction',
            "Retour pour correction: " . $motif
        );
    }

    /**
     * Clore la transmission actuelle
     */
    public function cloturerTransmission(string $reponse = null): void
    {
        $transmissionEnCours = $this->transmissionEnCours();

        if ($transmissionEnCours) {
            $transmissionEnCours->traiter($reponse);
        }
    }

    /**
     * Obtenir le nombre de transmissions
     */
    public function getNombreTransmissions(): int
    {
        return $this->transmissions()->count();
    }

    /**
     * Vérifier si le document a été transmis
     */
    public function aEteTransmis(): bool
    {
        return $this->transmissions()->exists();
    }
}
