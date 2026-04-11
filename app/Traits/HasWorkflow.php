<?php

namespace App\Traits;

use App\Models\Transmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWorkflow
{
    public function transmissions(): MorphMany
    {
        return $this->morphMany(Transmission::class, 'document');
    }

    public function transmissionEnCours(): ?Transmission
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest('date_transmission')
            ->first();
    }

    public function transmissionsEnAttente()
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->orderBy('date_transmission', 'desc')
            ->get();
    }

    public function historiqueTransmissions()
    {
        return $this->transmissions()
            ->with(['expediteur', 'destinataire'])
            ->orderBy('date_transmission', 'desc')
            ->get();
    }

    public function transmettreA(
        User $destinataire,
        string $actionAtttendue,
        string $commentaire = null,
        array $options = []
    ): Transmission {
        $transmission = $this->transmissions()->create([
            'expediteur_id'    => auth()->id(),
            'destinataire_id'  => $destinataire->id,
            'action_attendue'  => $actionAtttendue,
            'statut'           => 'en_attente',
            'commentaire'      => $commentaire,
            'priorite'         => $options['priorite'] ?? 'normale',
            'date_limite'      => $options['date_limite'] ?? null,
            'date_transmission' => now(),
            'documents_joints' => $options['documents_joints'] ?? null,
            'metadata'         => $options['metadata'] ?? null,
        ]);

        $destinataire->notify(
            new \App\Notifications\NouvelleTransmissionNotification($transmission)
        );

        return $transmission;
    }

    public function peutEtreTransmis(): bool
    {
        return $this->transmissionEnCours() === null;
    }

    public function estDestinataireActuel(User $user = null): bool
    {
        $user = $user ?? auth()->user();
        $transmission = $this->transmissionEnCours();
        return $transmission?->destinataire_id === $user->id;
    }

    public function getDestinataireActuel(): ?User
    {
        return $this->transmissionEnCours()?->destinataire;
    }

    public function getStatutWorkflow(): string
    {
        $transmission = $this->transmissionEnCours();
        if (!$transmission) return 'Aucune transmission en cours';
        return "Chez {$transmission->destinataire->name} — {$transmission->getActionLabel()}";
    }

    // ── NOUVELLES / MODIFIÉES ────────────────────────────────────

    public function estEnCoursDeTransmission(): bool
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->exists();
    }

    public function estEnCoursDeTransmissionPourAutrui(): bool
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->where('destinataire_id', '!=', auth()->id())
            ->exists();
    }

    public function estModifiable(): bool
    {
        return !$this->estEnCoursDeTransmission();
    }

    public function peutEtreModifiePar(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if ($this->estEnCoursDeTransmissionPourAutrui()) {
            return false;
        }

        if ($this->estDestinataireActuel($user)) {
            $transmission = $this->transmissionEnCours();
            return $transmission?->action_attendue === 'correction';
        }

        return ($this->created_by ?? null) === $user->id
            && !$this->estEnCoursDeTransmission();
    }

    public function retournerPourCorrection(string $motif): Transmission
    {
        $transmission = $this->transmissionEnCours();

        if (!$transmission) {
            throw new \Exception("Aucune transmission en cours");
        }

        if ($transmission->destinataire_id !== auth()->id()) {
            throw new \Exception("Vous n'êtes pas le destinataire de cette transmission.");
        }

        // 1. Rejeter la transmission
        $transmission->rejeter($motif);

        // 2. Remettre en brouillon
        $this->update(['statut' => 'brouillon']);

        // 3. Notifier l'expéditeur
        if ($transmission->expediteur) {
            \Filament\Notifications\Notification::make()
                ->title('Document retourné pour correction')
                ->warning()
                ->body(
                    'Votre document ' . ($this->numero ?? $this->reference_document ?? '') .
                        ' a été retourné. Motif : ' . $motif
                )
                ->sendToDatabase($transmission->expediteur);
        }

        // 4. Journal
        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['motif' => $motif])
            ->log(class_basename($this) . ' retourné pour correction');

        // 5. Transmission retour vers expéditeur
        return $this->transmettreA(
            $transmission->expediteur,
            'correction',
            'Retour pour correction : ' . $motif
        );
    }

    public function cloturerTransmission(string $reponse = null): void
    {
        $this->transmissionEnCours()?->traiter($reponse);
    }

    public function getNombreTransmissions(): int
    {
        return $this->transmissions()->count();
    }

    public function aEteTransmis(): bool
    {
        return $this->transmissions()->exists();
    }
}
