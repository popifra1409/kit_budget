<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transmission;
use Carbon\Carbon;

class TraiterTransmissionsExpirees extends Command
{
    protected $signature   = 'workflow:traiter-expirees';
    protected $description = 'Relancer les transmissions en retard et clôturer les très anciennes';

    public function handle(): void
    {
        $delaiRelanceJours  = config('workflow.delai_traitement_jours', 3);
        $delaiClotureSemaines = 2; // Clôture automatique après 2 semaines sans traitement

        // ── 1. Transmissions en retard → envoyer rappel ──────────
        $enRetard = Transmission::where('statut', 'en_attente')
            ->where('date_transmission', '<=', now()->subDays($delaiRelanceJours))
            ->whereNull('date_limite')
            ->orWhere(
                fn($q) =>
                $q->where('statut', 'en_attente')
                    ->where('date_limite', '<', now())
            )
            ->with('destinataire', 'document')
            ->get();

        foreach ($enRetard as $transmission) {
            $this->info("Relance : Transmission #{$transmission->id}");
            try {
                \Filament\Notifications\Notification::make()
                    ->title('⏰ Document en attente de traitement')
                    ->warning()
                    ->body(
                        "Le document " .
                            ($transmission->document?->numero ?? "#{$transmission->document_id}") .
                            " attend votre traitement depuis " .
                            $transmission->date_transmission->diffForHumans() . ". " .
                            "Action requise : " . $transmission->getActionLabel()
                    )
                    ->sendToDatabase($transmission->destinataire);
            } catch (\Exception $e) {
                $this->error("Erreur notification #{$transmission->id} : " . $e->getMessage());
            }
        }

        $this->info("✅ {$enRetard->count()} rappels envoyés");

        // ── 2. Transmissions très anciennes → clôture automatique ─
        $aCloturer = Transmission::where('statut', 'en_attente')
            ->where('date_transmission', '<=', now()->subWeeks($delaiClotureSemaines))
            ->with('expediteur', 'document')
            ->get();

        foreach ($aCloturer as $transmission) {
            $this->info("Clôture auto : Transmission #{$transmission->id}");

            $transmission->update([
                'statut'          => 'traite',
                'reponse'         => 'Clôturée automatiquement — délai dépassé',
                'date_traitement' => now(),
            ]);

            // Notifier l'expéditeur
            if ($transmission->expediteur) {
                try {
                    \Filament\Notifications\Notification::make()
                        ->title('🔔 Transmission clôturée automatiquement')
                        ->info()
                        ->body(
                            "La transmission du document " .
                                ($transmission->document?->numero ?? '') .
                                " a été clôturée automatiquement après " .
                                $delaiClotureSemaines . " semaines sans traitement."
                        )
                        ->sendToDatabase($transmission->expediteur);
                } catch (\Exception $e) {
                    \Log::warning('Notification clôture auto', ['error' => $e->getMessage()]);
                }
            }
        }

        $this->info("✅ {$aCloturer->count()} transmissions clôturées automatiquement");
    }
}
