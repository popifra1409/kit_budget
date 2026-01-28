<?php

namespace App\Notifications;

use App\Models\Transmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class NouvelleTransmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Transmission $transmission
    ) {}

    /**
     * Canaux de notification
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Notification email
     */
    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->transmission->document;
        $documentType = class_basename($this->transmission->document_type);

        return (new MailMessage)
            ->subject('Nouvelle transmission - ' . $this->transmission->getActionLabel())
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Vous avez reçu une nouvelle transmission de ' . $this->transmission->expediteur->name)
            ->line('**Document:** ' . $documentType . ' N°' . ($document->numero ?? $document->id))
            ->line('**Action attendue:** ' . $this->transmission->getActionLabel())
            ->when($this->transmission->commentaire, function ($mail) {
                return $mail->line('**Commentaire:** ' . $this->transmission->commentaire);
            })
            ->when($this->transmission->date_limite, function ($mail) {
                return $mail->line('**Date limite:** ' . $this->transmission->date_limite->format('d/m/Y'));
            })
            ->action('Voir le document', $this->getDocumentUrl())
            ->line('Merci de traiter cette demande dans les meilleurs délais.');
    }

    /**
     * Notification base de données (pour Filament)
     */
    public function toDatabase(object $notifiable): array
    {
        $document = $this->transmission->document;
        $documentType = class_basename($this->transmission->document_type);

        return [
            'transmission_id' => $this->transmission->id,
            'document_type' => $this->transmission->document_type,
            'document_id' => $this->transmission->document_id,
            'document_numero' => $document->numero ?? $document->id,
            'expediteur_name' => $this->transmission->expediteur->name,
            'action_attendue' => $this->transmission->action_attendue,
            'action_label' => $this->transmission->getActionLabel(),
            'commentaire' => $this->transmission->commentaire,
            'priorite' => $this->transmission->priorite,
            'date_limite' => $this->transmission->date_limite?->format('Y-m-d'),
            'url' => $this->getDocumentUrl(),
        ];
    }

    /**
     * Notification Filament (pour affichage dans l'interface)
     */
    public function toFilament(object $notifiable): FilamentNotification
    {
        $document = $this->transmission->document;
        $documentType = class_basename($this->transmission->document_type);

        return FilamentNotification::make()
            ->title('Nouvelle transmission')
            ->body(
                "De: {$this->transmission->expediteur->name}\n" .
                    "Document: {$documentType} N°" . ($document->numero ?? $document->id) . "\n" .
                    "Action: {$this->transmission->getActionLabel()}"
            )
            ->icon('heroicon-o-paper-airplane')
            ->iconColor($this->transmission->getPrioriteColor())
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Voir')
                    ->url($this->getDocumentUrl())
                    ->markAsRead(),
            ])
            ->persistent()
            ->send();
    }

    /**
     * Obtenir l'URL du document
     */
    protected function getDocumentUrl(): string
    {
        $document = $this->transmission->document;

        return match ($this->transmission->document_type) {
            'App\Models\BonCommande' => route('filament.admin.resources.bon-commandes.view', $document),
            'App\Models\Engagement' => route('filament.admin.resources.engagements.view', $document),
            default => route('filament.admin.pages.dashboard'),
        };
    }
}
