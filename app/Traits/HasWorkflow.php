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
        ?string $commentaire = null,  // ✅ nullable explicite
        array $options = []
    ): Transmission {

        // ✅ Vérifier que l'expéditeur est authentifié
        $expediteurId = auth()->id();
        if (!$expediteurId) {
            throw new \Exception('Aucun utilisateur authentifié pour créer la transmission.');
        }

        $transmission = $this->transmissions()->create([
            'expediteur_id'    => $expediteurId,
            'destinataire_id'  => $destinataire->id,
            'action_attendue'  => $actionAtttendue,
            'statut'           => 'en_attente',
            'commentaire'      => $commentaire,
            'priorite'         => $options['priorite']        ?? 'normale',
            'date_limite'      => $options['date_limite']     ?? null,
            'date_transmission' => now(),
            'documents_joints' => $options['documents_joints'] ?? null,
            'metadata'         => $options['metadata']        ?? null,
        ]);

        // ✅ Notifier avec try/catch pour ne pas bloquer si notification échoue
        try {
            $destinataire->notify(
                new \App\Notifications\NouvelleTransmissionNotification($transmission)
            );
        } catch (\Exception $e) {
            \Log::warning('Notification transmission échouée', ['error' => $e->getMessage()]);
        }

        return $transmission;
    }

    public function peutEtreTransmis(): bool
    {
        return $this->transmissionEnCours() === null;
    }

    public function estDestinataireActuel(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) return false;
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
        if (!$user) return false;

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

    // ✅ FIX MAJEUR — ne crée plus de transmission de retour
    // Retourner = simplement rejeter + remettre en brouillon
    public function retournerPourCorrection(?string $motif = null): void
    {
        $transmission = $this->transmissionEnCours();

        if (!$transmission) {
            throw new \Exception("Aucune transmission en cours");
        }

        // ✅ Comparaison stricte après cast
        if ((int) $transmission->destinataire_id !== (int) auth()->id()) {
            throw new \Exception(
                "Vous n'êtes pas le destinataire de cette transmission. " .
                    "Destinataire attendu : ID {$transmission->destinataire_id}, " .
                    "vous êtes : ID " . auth()->id()
            );
        }

        $transmission->updateQuietly([
            'statut'          => 'retourne',
            'motif_retour'    => $motif,
            'reponse'         => $motif,
            'date_traitement' => now(),
        ]);

        $this->updateQuietly(['statut' => 'brouillon']);

        if ($transmission->expediteur) {
            try {
                \Filament\Notifications\Notification::make()
                    ->title('↩ Document retourné pour correction')
                    ->warning()
                    ->body(
                        'Votre document ' . ($this->numero ?? '') .
                            ' a été retourné par ' . auth()->user()?->name .
                            ($motif ? '. Motif : ' . $motif : '')
                    )
                    ->sendToDatabase($transmission->expediteur);
            } catch (\Exception $e) {
                \Log::warning('Notification retour échouée', ['error' => $e->getMessage()]);
            }
        }
    }

    // ✅ nullable explicite
    public function cloturerTransmission(?string $reponse = null): void
    {
        $transmission = $this->transmissionEnCours();
        if (!$transmission) return;

        $transmission->updateQuietly([
            'statut'          => 'traite',
            'reponse'         => $reponse,
            'date_traitement' => now(),
        ]);

        // Notifier l'expéditeur
        if ($transmission->expediteur) {
            try {
                \Filament\Notifications\Notification::make()
                    ->title('✅ Transmission clôturée')
                    ->success()
                    ->body(
                        'Le document ' . ($this->numero ?? '') .
                            ' a été traité par ' . auth()->user()?->name
                    )
                    ->sendToDatabase($transmission->expediteur);
            } catch (\Exception $e) {
                \Log::warning('Notification clôture échouée', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * ✅ Émetteur peut rappeler sa transmission dans le délai configuré
     */
    public function peutAnnulerSaTransmission(?int $delaiMinutes = null): bool
    {
        $transmission = $this->transmissions()
            ->where('expediteur_id', auth()->id())
            ->where('statut', 'en_attente')
            ->latest('date_transmission')
            ->first();

        if (!$transmission) return false;

        // Délai par défaut : 30 minutes (configurable)
        $delai = $delaiMinutes ?? config('workflow.delai_annulation_minutes', 30);

        return $transmission->date_transmission
            ->addMinutes($delai)
            ->isFuture();
    }

    public function annulerMaTransmission(?string $raison = null): void
    {
        $transmission = $this->transmissions()
            ->where('expediteur_id', auth()->id())
            ->where('statut', 'en_attente')
            ->latest('date_transmission')
            ->first();

        if (!$transmission) {
            throw new \Exception("Aucune transmission active trouvée.");
        }

        if (!$this->peutAnnulerSaTransmission()) {
            $delai = config('workflow.delai_annulation_minutes', 30);
            throw new \Exception(
                "Délai de rappel dépassé ({$delai} min). " .
                    "Contactez le destinataire pour qu'il retourne le document."
            );
        }

        $transmission->annuler($raison ?? "Rappelé par l'émetteur");

        // Notifier le destinataire
        if ($transmission->destinataire) {
            try {
                \Filament\Notifications\Notification::make()
                    ->title('↩ Transmission annulée')
                    ->warning()
                    ->body(
                        "La transmission du document " . ($this->numero ?? '') .
                            " a été annulée par " . auth()->user()?->name .
                            ($raison ? ". Raison : {$raison}" : '')
                    )
                    ->sendToDatabase($transmission->destinataire);
            } catch (\Exception $e) {
                \Log::warning('Notification annulation échouée', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * ✅ Temps restant avant expiration du délai d'annulation
     */
    public function tempsRestantAnnulation(?int $delaiMinutes = null): ?string
    {
        $transmission = $this->transmissions()
            ->where('expediteur_id', auth()->id())
            ->where('statut', 'en_attente')
            ->latest('date_transmission')
            ->first();

        if (!$transmission) return null;

        $delai = $delaiMinutes ?? config('workflow.delai_annulation_minutes', 30);
        $expiration = $transmission->date_transmission->addMinutes($delai);

        if ($expiration->isPast()) return null;

        $minutes = now()->diffInMinutes($expiration);
        return "{$minutes} min restante(s)";
    }

    /**
     * ✅ Vérifier si la transmission est en retard
     */
    public function transmissionEnRetard(): bool
    {
        $transmission = $this->transmissionEnCours();
        return $transmission?->estEnRetard() ?? false;
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
