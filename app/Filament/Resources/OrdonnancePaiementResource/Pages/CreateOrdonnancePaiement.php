<?php

namespace App\Filament\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Resources\OrdonnancePaiementResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\Engagement;
use App\Models\OrdonnancePaiement;

class CreateOrdonnancePaiement extends CreateRecord
{
    protected static string $resource = OrdonnancePaiementResource::class;

    /**
     * ✅ Intercepter la création complète pour utiliser la méthode de l'engagement
     */
    public function create(bool $another = false): void
    {
        $this->authorizeAccess();

        try {
            // Valider le formulaire
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $engagementId = $data['engagement_id'] ?? null;

            if (!$engagementId) {
                throw new \Exception('Aucun engagement sélectionné');
            }

            // Charger l'engagement avec ses relations
            $engagement = Engagement::with('engageable', 'beneficiaire')->findOrFail($engagementId);

            // ✅ CRÉER LES ORDONNANCES VIA LA MÉTHODE DE L'ENGAGEMENT
            $ordonnances = $engagement->creerOrdonnancesPaiement();

            // Stocker l'OP standard comme record principal pour la redirection
            if (isset($ordonnances['standard'])) {
                $this->record = $ordonnances['standard'];
            } else {
                // Si pas d'OP standard, prendre la première disponible
                $this->record = reset($ordonnances);
            }

            // ✅ Message de succès détaillé
            $message = $this->construireMessageSucces($ordonnances);

            Notification::make()
                ->title('✅ Ordonnances créées avec succès')
                ->success()
                ->body($message)
                ->duration(10000)
                ->send();

            // Log pour traçabilité
            \Log::info('Ordonnances créées depuis Filament', [
                'engagement_id' => $engagement->id,
                'engagement_numero' => $engagement->numero,
                'op_standard' => $ordonnances['standard']->numero ?? null,
                'op_impot' => $ordonnances['impot']->numero ?? null,
                'utilisateur' => auth()->user()->name,
            ]);

            // Appeler les hooks après création
            $this->callHook('afterCreate');

            // Redirection
            $this->redirect($this->getRedirectUrl());
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Erreur lors de la création')
                ->danger()
                ->body($e->getMessage())
                ->persistent()
                ->send();

            \Log::error('Erreur création OP depuis Filament', [
                'engagement_id' => $engagementId ?? null,
                'erreur' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Ne pas rediriger en cas d'erreur
            return;
        }
    }

    /**
     * ✅ Construire un message de succès détaillé
     */
    protected function construireMessageSucces(array $ordonnances): string
    {
        $details = [];

        if (isset($ordonnances['standard'])) {
            $op = $ordonnances['standard'];
            $details[] = sprintf(
                "📄 **OP Standard %s**\n   → Bénéficiaire : %s\n   → Montant : %s FCFA",
                $op->numero,
                $op->beneficiaire->raison_sociale ?? $op->beneficiaire->nom_complet ?? $op->beneficiaire->name ?? 'N/A',
                number_format($op->montant_net, 0, ',', ' ')
            );
        }

        if (isset($ordonnances['impot'])) {
            $op = $ordonnances['impot'];
            $details[] = sprintf(
                "💰 **OP Impôt %s**\n   → Bénéficiaire : Trésor Public\n   → Montant : %s FCFA",
                $op->numero,
                number_format($op->montant_net, 0, ',', ' ')
            );
        }

        $total = count($ordonnances);
        $header = $total === 1
            ? "1 ordonnance créée :"
            : "{$total} ordonnances créées :";

        return $header . "\n\n" . implode("\n\n", $details);
    }

    /**
     * ✅ Redirection vers la liste avec filtre sur l'engagement
     */
    protected function getRedirectUrl(): string
    {
        $engagementId = $this->data['engagement_id'] ?? null;

        // Si on a un engagement, rediriger vers la liste filtrée
        if ($engagementId) {
            return $this->getResource()::getUrl('index', [
                'tableFilters' => [
                    'engagement_id' => ['value' => $engagementId],
                ],
            ]);
        }

        // Sinon, redirection standard vers la vue de l'OP créée
        if ($this->record) {
            return $this->getResource()::getUrl('view', ['record' => $this->record]);
        }

        // Fallback vers la liste
        return $this->getResource()::getUrl('index');
    }

    /**
     * ✅ Désactiver la notification par défaut de Filament
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return null; // On gère la notification manuellement dans create()
    }

    /**
     * ✅ Hook après création (optionnel)
     */
    protected function afterCreate(): void
    {
        // Actions supplémentaires si nécessaire
        // Par exemple : envoyer un email, créer une notification système, etc.
    }
}
