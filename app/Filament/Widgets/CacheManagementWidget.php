<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CacheManagementWidget extends Widget
{
    protected static string $view = 'filament.widgets.cache-management-widget';

    protected static ?int $sort = 999; // Afficher en dernier

    protected int | string | array $columnSpan = 'full';

    /**
     * Afficher uniquement pour les admins
     */
    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    /**
     * Vider le cache de l'application
     */
    public function clearApplicationCache(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            Notification::make()
                ->title('Cache vidé avec succès')
                ->success()
                ->body('Le cache de l\'application a été nettoyé.')
                ->send();

            $this->dispatch('cache-cleared');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->danger()
                ->body('Impossible de vider le cache : ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Optimiser l'application
     */
    public function optimizeApplication(): void
    {
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            Notification::make()
                ->title('Application optimisée')
                ->success()
                ->body('L\'application a été optimisée avec succès.')
                ->send();

            $this->dispatch('app-optimized');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->danger()
                ->body('Erreur lors de l\'optimisation : ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Vider le cache de session
     */
    public function clearSessionCache(): void
    {
        try {
            // Note: Ceci vide uniquement la session de l'utilisateur actuel
            session()->flush();
            session()->regenerate();

            Notification::make()
                ->title('Session réinitialisée')
                ->warning()
                ->body('Votre session a été nettoyée. Vous allez être déconnecté.')
                ->send();

            // Rediriger vers la page de login après 2 secondes
            $this->dispatch('session-cleared');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->danger()
                ->body('Impossible de réinitialiser la session.')
                ->send();
        }
    }

    /**
     * Obtenir les statistiques de cache
     */
    public function getCacheStats(): array
    {
        return [
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'session_lifetime' => config('session.lifetime') . ' minutes',
            'queue_driver' => config('queue.default'),
        ];
    }
}
