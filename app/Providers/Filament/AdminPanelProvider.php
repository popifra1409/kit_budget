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
use App\Models\ParametresFournisseur;
use Filament\Navigation\NavigationGroup;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;
use App\Filament\Pages\Auth\Login;

/**
 * AdminPanelProvider final avec données du fournisseur depuis la base de données
 * VERSION CORRIGÉE - Sans erreur formatTelephoneHref()
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ===================================
        // RÉCUPÉRATION SÉCURISÉE DES PARAMÈTRES
        // ===================================
        $structure = $this->getParametresSecurise();
        $fournisseur = $this->getFournisseurSecurise();

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ->login()
            ->login(Login::class)

            // 🎨 PALETTE DE COULEURS PERSONNALISÉE
            ->colors([
                'primary' => Color::hex($fournisseur->couleur_principale ?? '#0ea5e9'),
                'success' => Color::hex('#059669'),
                'danger' => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info' => Color::hex('#64748b'),
            ])

            // ===================================
            // BRANDING PERSONNALISÉ - LOGO ÉDITEUR
            // ===================================
            ->brandName($fournisseur->nom_logiciel ?? 'Budget Manager')
            ->brandLogo(fn() => $fournisseur->logo_url ?? asset('images/logo-editeur.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn() => $fournisseur->logo_url ?? asset('images/favicon.png'))

            // ===================================
            // SIDEBAR CONFIGURATION
            // ===================================
            ->sidebarCollapsibleOnDesktop()

            // 📂 ORDRE DES GROUPES DE NAVIGATION
            ->navigationGroups([
                'Commandes & Engagement',
                'Document',
                'Gestion Budgétaire',
                'Cadre Logique',
                'Configuration Budget',
                'Configuration',
                'Audit',
                'Administration',
            ])

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

            // ===================================
            // 🎯 BADGE LICENCE (Header)
            // ===================================
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): HtmlString => $this->renderBadgeLicence($structure, $fournisseur)
            )

            // ===================================
            // 🚀 ACTIONS RAPIDES (Header)
            // ===================================
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
                    
                    <!-- Badge: Exercice en cours -->
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">' . now()->year . '</span>
                    </div>
                    
                </div>
                ')
            )

            // ===================================
            // 📄 FOOTER AVEC COPYRIGHT
            // ===================================
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn(): HtmlString => $this->renderFooter($structure, $fournisseur)
            );
    }

    /**
     * Render badge licence
     */
    private function renderBadgeLicence($structure, $fournisseur): HtmlString
    {
        // Ne pas afficher si désactivé
        if (!$fournisseur->afficher_badge_licence) {
            return new HtmlString('');
        }

        return new HtmlString('
            <div class="flex items-center gap-2 px-3 py-1.5 me-3 rounded-lg bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900/20 dark:to-blue-900/20 border border-purple-200 dark:border-purple-800">
                <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <div class="text-xs">
                    <span class="font-semibold text-purple-700 dark:text-purple-300">Licence accordée à:</span>
                    <span class="font-bold text-purple-900 dark:text-purple-100 ml-1">' . e($structure->sigle ?? $structure->nom_structure) . '</span>
                </div>
            </div>
        ');
    }

    /**
     * Render footer complet depuis la base de données
     */
    private function renderFooter($structure, $fournisseur): HtmlString
    {
        // Ne pas afficher si désactivé
        if (!$fournisseur->afficher_footer) {
            return new HtmlString('');
        }

        $contactFooter = $fournisseur->contact_footer;
        $docLinks = $fournisseur->documentation_links;

        return new HtmlString('
            <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 mt-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    
                    <!-- Section principale -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        
                        <!-- Colonne 1: À propos du logiciel -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                ' . e($fournisseur->nom_logiciel) . '
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                                ' . e($fournisseur->description_logiciel ?? 'Système de gestion développé par ' . $fournisseur->nom_societe) . '
                            </p>
                        </div>
                        
                        <!-- Colonne 2: Informations Structure Cliente -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Déployé pour
                            </h3>
                            <p class="text-xs text-gray-800 dark:text-gray-300 font-medium">' . e($structure->nom_complet ?? 'N/A') . '</p>
                            ' . ($structure->ville ? '<p class="text-xs text-gray-600 dark:text-gray-400 mt-1">' . e($structure->ville) . ($structure->pays ? ', ' . e($structure->pays) : '') . '</p>' : '') . '
                            ' . ($structure->email ? '<p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                ' . e($structure->email) . '
                            </p>' : '') . '
                        </div>
                        
                        <!-- Colonne 3: Support & Contact Fournisseur -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Support & Assistance
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                <a href="mailto:' . e($contactFooter['email_support']) . '" class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                                    ' . e($contactFooter['email_support']) . '
                                </a>
                            </p>
                            ' . ($contactFooter['telephone_support'] ? '<p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Tél: <a href="tel:' . e($this->formatTelephone($contactFooter['telephone_support'])) . '" class="hover:text-blue-600 dark:hover:text-blue-400 transition">' . e($contactFooter['telephone_support']) . '</a>
                            </p>' : '') . '
                            ' . ($contactFooter['telephone_urgence'] ? '<p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Urgence 24/7: <a href="tel:' . e($this->formatTelephone($contactFooter['telephone_urgence'])) . '" class="hover:text-red-600 dark:hover:text-red-400 transition font-semibold">' . e($contactFooter['telephone_urgence']) . '</a>
                            </p>' : '') . '
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                ' . ($docLinks['documentation'] ? '<a href="' . e($docLinks['documentation']) . '" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400 transition">Documentation</a>' : 'Documentation') . ' • 
                                ' . ($docLinks['guide_utilisateur'] ? '<a href="' . e($docLinks['guide_utilisateur']) . '" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400 transition">Guide utilisateur</a>' : 'Guide utilisateur') . '
                            </p>
                        </div>
                    </div>
                    
                    <!-- Séparateur -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                            
                            <!-- Copyright -->
                            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12zm-1-5.5a1 1 0 112 0V12a1 1 0 11-2 0V10.5z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-medium">' . e($fournisseur->copyright_complet) . '</span>
                            </div>
                            
                            <!-- Version & Licence -->
                            <div class="flex items-center gap-4 text-xs">
                                <span class="px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-mono">
                                    ' . e($fournisseur->version_complete) . '
                                </span>
                                <span class="px-2 py-1 rounded bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 font-medium flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Licence Active
                                </span>
                                ' . ($fournisseur->site_web ? '<a href="' . e($fournisseur->site_web) . '" target="_blank" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition">
                                    ' . e($fournisseur->nom_societe) . '
                                </a>' : '') . '
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        ');
    }

    /**
     * Formater un numéro de téléphone pour un lien tel:
     * Supprime les espaces, parenthèses, tirets, etc.
     */
    private function formatTelephone(?string $telephone): string
    {
        if (!$telephone) {
            return '';
        }

        // Supprimer tout sauf les chiffres et le +
        return preg_replace('/[^0-9+]/', '', $telephone);
    }

    /**
     * Récupérer les paramètres de structure de manière SÉCURISÉE
     */
    private function getParametresSecurise(): object
    {
        if (env('INSTALLATION_MODE', false)) {
            return $this->getParametresDefaut();
        }

        if (!Schema::hasTable('parametres_structure')) {
            return $this->getParametresDefaut();
        }

        try {
            $structure = ParametresStructure::getParametres();
            return $structure ?? $this->getParametresDefaut();
        } catch (\Exception $e) {
            \Log::warning('Impossible de charger parametres_structure: ' . $e->getMessage());
            return $this->getParametresDefaut();
        }
    }

    /**
     * Récupérer les paramètres fournisseur de manière SÉCURISÉE
     */
    private function getFournisseurSecurise(): object
    {
        if (env('INSTALLATION_MODE', false)) {
            return $this->getFournisseurDefaut();
        }

        if (!Schema::hasTable('parametres_fournisseur')) {
            return $this->getFournisseurDefaut();
        }

        try {
            $fournisseur = ParametresFournisseur::getOrCreateParametres();
            return $fournisseur;
        } catch (\Exception $e) {
            \Log::warning('Impossible de charger parametres_fournisseur: ' . $e->getMessage());
            return $this->getFournisseurDefaut();
        }
    }

    /**
     * Retourner des paramètres structure par défaut
     */
    private function getParametresDefaut(): object
    {
        return (object) [
            'nom_structure' => 'Gestion Budget',
            'sigle' => 'GB',
            'logo_url' => null,
            'nom_complet' => 'Gestion Budget (GB)',
            'ville' => null,
            'pays' => null,
            'email' => null,
        ];
    }

    /**
     * Retourner des paramètres fournisseur par défaut
     */
    private function getFournisseurDefaut(): object
    {
        return (object) [
            'nom_societe' => 'Votre Société',
            'nom_logiciel' => 'Budget Manager',
            'version_logiciel' => '1.0.0',
            'version_complete' => 'v1.0.0',
            'description_logiciel' => 'Système de gestion budgétaire et financière',
            'logo_url' => null,
            'couleur_principale' => '#0ea5e9',
            'afficher_footer' => true,
            'afficher_badge_licence' => true,
            'copyright_complet' => '© ' . date('Y') . ' Votre Société - Tous droits réservés',
            'contact_footer' => [
                'email_support' => 'support@votresociete.com',
                'telephone_support' => '+237 00 00 00 00',
                'telephone_urgence' => null,
                'horaires' => null,
            ],
            'documentation_links' => [
                'documentation' => null,
                'guide_utilisateur' => null,
                'site_web' => null,
            ],
            'site_web' => null,
        ];
    }
}
