<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Models\ParametresStructure;
use Filament\Navigation\NavigationGroup;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ===================================
        // RÉCUPÉRATION SÉCURISÉE DES PARAMÈTRES
        // ===================================
        $structure = $this->getParametresSecurise();

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()

            // 🎨 PALETTE DE COULEURS PERSONNALISÉE
            ->colors([
                'primary' => Color::hex('#0ea5e9'),
                'success' => Color::hex('#059669'),
                'danger' => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info' => Color::hex('#64748b'),
            ])

            // ===================================
            // BRANDING PERSONNALISÉ
            // ===================================
            ->brandName($structure->sigle ?? $structure->nom_structure ?? 'Administration')
            ->brandLogo(fn() => $structure->logo_url ?? null)
            ->brandLogoHeight('2.5rem')
            ->favicon($structure->logo_url ?? null)

            // ===================================
            // SIDEBAR CONFIGURATION
            // ===================================
            ->sidebarCollapsibleOnDesktop()

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => new HtmlString('
                <div class="flex items-center gap-2 me-4">
                    
                    <!-- Action: Nouveau Bon de Commande -->
                    <a href="' . route('filament.admin.resources.bon-commandes.create') . '" 
                       class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 rounded-lg transition shadow-sm hover:shadow-md transform hover:-translate-y-0.5"
                       title="Nouveau Bon de Commande">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="hidden lg:inline font-medium">Nouveau BC</span>
                    </a>
                    
                    <!-- Action: Nouvel Engagement -->
                    <a href="' . route('filament.admin.resources.engagements.create') . '" 
                       class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30 rounded-lg transition border border-green-200 dark:border-green-800"
                       title="Nouvel Engagement">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="hidden lg:inline font-medium">Engagement</span>
                    </a>
                    
                    <!-- Action: Nouveau Mémoire de Dépense -->
                    <a href="' . route('filament.admin.resources.memoire-depenses.create') . '" 
                       class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-orange-700 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 hover:bg-orange-100 dark:hover:bg-orange-900/30 rounded-lg transition border border-orange-200 dark:border-orange-800"
                       title="Nouveau Mémoire de Dépense">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="hidden lg:inline font-medium">Mémoire</span>
                    </a>
                    
                    <!-- Divider -->
                    <div class="border-l border-blue-300 dark:border-blue-600 h-8 mx-1"></div>
                    
                    <!-- Divider -->
                    <div class="border-l border-gray-300 dark:border-gray-600 h-8 mx-1"></div>
                    
                    <!-- Badge: Exercice en cours -->
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">' . now()->year . '</span>
                    </div>
                    
                </div>
            ')
            );
    }

    /**
     * Récupérer les paramètres de structure de manière SÉCURISÉE
     * Retourne un objet par défaut si la table n'existe pas encore
     */
    private function getParametresSecurise(): object
    {
        // Mode installation : retourner des valeurs par défaut
        if (env('INSTALLATION_MODE', false)) {
            return $this->getParametresDefaut();
        }

        // Vérifier que la table existe
        if (!Schema::hasTable('parametres_structure')) {
            return $this->getParametresDefaut();
        }

        // Tenter de charger les paramètres
        try {
            $structure = ParametresStructure::getParametres();

            if ($structure) {
                return $structure;
            }

            return $this->getParametresDefaut();
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'application
            \Log::warning('Impossible de charger parametres_structure: ' . $e->getMessage());
            return $this->getParametresDefaut();
        }
    }

    /**
     * Retourner des paramètres par défaut
     */
    private function getParametresDefaut(): object
    {
        return (object) [
            'nom_structure' => 'Gestion Budget',
            'sigle' => 'GB',
            'logo_url' => null,
        ];
    }
}
