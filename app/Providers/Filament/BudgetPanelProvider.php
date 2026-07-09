<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
use App\Models\ParametresFournisseur;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;
use App\Filament\Pages\Auth\Login;
use Filament\Navigation\MenuItem;
use Illuminate\Support\Facades\Blade;

class BudgetPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->bind(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            fn() => new class implements \Filament\Http\Responses\Auth\Contracts\LoginResponse {
                public function toResponse($request): \Symfony\Component\HttpFoundation\Response
                {
                    return \Illuminate\Support\Facades\Response::make('', 302, [
                        'Location' => '/portal',
                    ]);
                }
            }
        );
    }

    public function panel(Panel $panel): Panel
    {
        $structure = $this->getParametresSecurise();
        $fournisseur = $this->getFournisseurSecurise();

        return $panel
            ->id('budget')
            ->path('budget')
            ->login(Login::class)
            ->profile()

            ->colors([
                'primary' => Color::hex($fournisseur->couleur_principale ?? '#0ea5e9'),
                'success' => Color::hex('#059669'),
                'danger' => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info' => Color::hex('#64748b'),
            ])

            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->brandName(($fournisseur->nom_logiciel ?? 'Budget Manager') . ' — Budget')
            ->brandLogo(function () use ($fournisseur) {
                $logo = $fournisseur->logo_url;
                if (!$logo)
                    return asset('images/logo.png');
                // Si déjà une URL complète → retourner tel quel
                if (str_starts_with($logo, 'http'))
                    return $logo;
                // Sinon forcer l'URL absolue depuis la racine
                return asset($logo);
            })
            ->brandLogoHeight('2.5rem')
            ->favicon(fn() => $fournisseur->logo ?? asset('images/favicon.png'))

            ->sidebarCollapsibleOnDesktop()

            ->userMenuItems([
                MenuItem::make()
                    ->label('Règlages')
                    ->url('')
                    ->icon('heroicon-o-cog-6-tooth')
            ])

            // ── Navigation du module Budget ──────────────────────────────
            ->navigationGroups([
                'Commandes & Engagement',
                'Régies & Menu Dépenses',
                'Fournisseurs & Documents',
                'Gestion Budgétaire',
                'Cadre Logique',
                'Contrôle & Suivi',
                'Configuration Budget',
                'Paramétrage',
                'Audit',
                'Administration',
            ])

            // ── Découverte dans les sous-dossiers Budget/ ────────────────
            ->discoverResources(
                in: app_path('Filament/Budget/Resources'),
                for: 'App\\Filament\\Budget\\Resources'
            )
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Budget/Pages'),
                for: 'App\\Filament\\Budget\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Budget/Widgets'),
                for: 'App\\Filament\\Budget\\Widgets'
            )

            // ── Widgets explicites (ceux restés dans Filament/Widgets/) ──
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\WelcomeWidget::class,
                // \App\Filament\Budget\Widgets\AgentBudgetaireWidget::class,
            ])

            ->pages([
                \App\Filament\Pages\Dashboard::class,
                \App\Filament\Budget\Pages\DashboardRegie::class,
            ])

            // ── Render hooks ─────────────────────────────────────────────
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => auth()->check()
                    ? Blade::render('@livewire(\'agent-budgetaire-widget\')')
                    : ''
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->renderModuleSwitcher()
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->renderActionsRapides()
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): HtmlString => $this->renderBadgeLicence($structure, $fournisseur)
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn(): HtmlString => $this->renderFooter($structure, $fournisseur)
            )
            ->renderHook(
                'panels::body.end',
                fn() => new \Illuminate\Support\HtmlString('
        <script>
            window.addEventListener("open-url-new-tab", (e) => {
                window.open(e.detail.url, "_blank");
            });
        </script>
    ')
            )

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
                'module.access:budget',
            ]);
    }

    // =========================================================================
    // SWITCHER DE MODULE — pill-tabs dans le header
    // =========================================================================
    private function renderModuleSwitcher(): HtmlString
    {
        $modules = [
            'budget' => ['label' => 'Budget', 'icon' => '💰', 'url' => '/budget'],
            'comptable' => ['label' => 'Comptable', 'icon' => '📒', 'url' => '/comptable'],
            'marches' => ['label' => 'Marchés', 'icon' => '📋', 'url' => '/marches'],
        ];

        $html = '<div class="flex items-center gap-1 me-3 p-1 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">';

        foreach ($modules as $key => $module) {
            $isActive = $key === 'budget';
            $activeClass = $isActive
                ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold text-gray-900 dark:text-white'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 dark:hover:bg-gray-700/60';

            $html .= '
            <a href="' . ($isActive ? '#' : e($module['url'])) . '"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition-all ' . $activeClass . '">
                <span>' . $module['icon'] . '</span>
                <span class="hidden md:inline">' . e($module['label']) . '</span>
            </a>';
        }

        $html .= '</div>';
        return new HtmlString($html);
    }

    // =========================================================================
    // ACTIONS RAPIDES — Nouveau BC, Engagement, Mémoire
    // =========================================================================
    private function renderActionsRapides(): HtmlString
    {
        $urlBC = $this->getResourceUrl('App\Filament\Budget\Resources\BonCommandeResource', 'create');
        $urlEngagement = $this->getResourceUrl('App\Filament\Budget\Resources\EngagementResource', 'create');
        $urlMemoire = $this->getResourceUrl('App\Filament\Budget\Resources\MemoireDepenseResource', 'create');

        return new HtmlString('
        <div class="flex items-center gap-2 me-4">
        <a href="/portal"
           class="flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                  text-gray-500 dark:text-gray-400
                  hover:text-gray-700 dark:hover:text-gray-200
                  bg-gray-100 dark:bg-gray-800
                  hover:bg-gray-200 dark:hover:bg-gray-700
                  rounded-lg transition border border-gray-200 dark:border-gray-700
                  group"
           title="Retour au portail">
            <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span class="hidden lg:inline">Portail</span>
        </a>

        <div class="border-l border-gray-300 dark:border-gray-600 h-8 mx-1"></div>
        <div class="flex items-center gap-2 me-4">

            ' . ($urlBC ? '
            <a href="' . e($urlBC) . '"
               class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white
                      bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                      rounded-lg transition shadow-sm hover:shadow-md transform hover:-translate-y-0.5"
               title="Nouveau Bon de Commande">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0
                             01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="hidden lg:inline font-medium">Nouveau BC</span>
            </a>' : '') . '

            ' . ($urlEngagement ? '
            <a href="' . e($urlEngagement) . '"
               class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-green-700
                      dark:text-green-400 bg-green-50 dark:bg-green-900/20
                      hover:bg-green-100 dark:hover:bg-green-900/30 rounded-lg transition
                      border border-green-200 dark:border-green-800"
               title="Nouvel Engagement">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11
                             0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21
                             12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="hidden lg:inline font-medium">Engagement</span>
            </a>' : '') . '

            ' . ($urlMemoire ? '
            <a href="' . e($urlMemoire) . '"
               class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-orange-700
                      dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20
                      hover:bg-orange-100 dark:hover:bg-orange-900/30 rounded-lg transition
                      border border-orange-200 dark:border-orange-800"
               title="Nouveau Mémoire de Dépense">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0
                             01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="hidden lg:inline font-medium">Mémoire</span>
            </a>' : '') . '

            <div class="border-l border-gray-300 dark:border-gray-600 h-8 mx-1"></div>

            <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg
                        bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0
                             00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">
                    ' . now()->year . '
                </span>
            </div>

        </div>');
    }

    private function getResourceUrl(string $resourceClass, string $page = 'index'): ?string
    {
        try {
            if (!class_exists($resourceClass))
                return null;
            return $resourceClass::getUrl($page);
        } catch (\Exception $e) {
            return null;
        }
    }

    // =========================================================================
    // BADGE LICENCE
    // =========================================================================
    private function renderBadgeLicence($structure, $fournisseur): HtmlString
    {
        if (!($fournisseur->afficher_badge_licence ?? true)) {
            return new HtmlString('');
        }

        return new HtmlString('
            <div class="flex items-center gap-2 px-3 py-1.5 me-3 rounded-lg
                        bg-gradient-to-r from-purple-50 to-blue-50
                        dark:from-purple-900/20 dark:to-blue-900/20
                        border border-purple-200 dark:border-purple-800">
                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0
                             01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622
                             5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <div class="text-xs">
                    <span class="font-semibold text-purple-700 dark:text-purple-300">Licence accordée à :</span>
                    <span class="font-bold text-purple-900 dark:text-purple-100 ml-1">
                        ' . e($structure->sigle ?? $structure->nom_structure) . '
                    </span>
                </div>
            </div>
        ');
    }

    // =========================================================================
    // FOOTER
    // =========================================================================
    private function renderFooter($structure, $fournisseur): HtmlString
    {
        if (!($fournisseur->afficher_footer ?? true)) {
            return new HtmlString('');
        }

        $contactFooter = $fournisseur->contact_footer ?? [];
        $docLinks = $fournisseur->documentation_links ?? [];

        return new HtmlString('
        <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 mt-auto">
            <div class="max-w-7xl mx-auto px-4 py-4">
                <div class="flex flex-col md:flex-row justify-between items-center gap-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        ' . e($fournisseur->copyright_complet ?? '© ' . date('Y')) . '
                    </p>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/30
                                     text-blue-700 dark:text-blue-300 font-mono text-xs">
                            ' . e($fournisseur->version_complete ?? 'v1.0.0') . '
                        </span>
                        <span class="px-2 py-1 rounded bg-green-100 dark:bg-green-900/30
                                     text-green-700 dark:text-green-300 text-xs font-medium">
                            💰 Module Budget
                        </span>
                    </div>
                </div>
            </div>
        </footer>
        ');
    }

    // =========================================================================
    // PARAMÈTRES SÉCURISÉS
    // =========================================================================
    private function getParametresSecurise(): object
    {
        if (env('INSTALLATION_MODE', false) || !Schema::hasTable('parametres_structure')) {
            return $this->getParametresDefaut();
        }
        try {
            return ParametresStructure::getParametres() ?? $this->getParametresDefaut();
        } catch (\Exception $e) {
            \Log::warning('parametres_structure: ' . $e->getMessage());
            return $this->getParametresDefaut();
        }
    }

    private function getFournisseurSecurise(): object
    {
        if (env('INSTALLATION_MODE', false) || !Schema::hasTable('parametres_fournisseur')) {
            return $this->getFournisseurDefaut();
        }
        try {
            return ParametresFournisseur::getOrCreateParametres();
        } catch (\Exception $e) {
            \Log::warning('parametres_fournisseur: ' . $e->getMessage());
            return $this->getFournisseurDefaut();
        }
    }

    private function getParametresDefaut(): object
    {
        return (object) [
            'nom_structure' => 'Gestion Budget',
            'sigle' => 'GB',
            'logo' => null,
            'nom_complet' => 'Gestion Budget',
            'ville' => null,
            'pays' => null,
            'email' => null,
        ];
    }

    private function getFournisseurDefaut(): object
    {
        return (object) [
            'nom_societe' => 'Votre Société',
            'nom_logiciel' => 'Budget Manager',
            'version_complete' => 'v1.0.0',
            'logo' => null,
            'couleur_principale' => '#0ea5e9',
            'afficher_footer' => true,
            'afficher_badge_licence' => true,
            'copyright_complet' => '© ' . date('Y') . ' Votre Société',
            'contact_footer' => ['email_support' => 'support@votresociete.com'],
            'documentation_links' => [],
            'site_web' => null,
        ];
    }
}
