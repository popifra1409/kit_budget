<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Providers\Filament\Concerns\HasCommonPanelBootstrap;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

/**
 * FinancierPanelProvider — Module Gestion Comptable et Traitement des Flux
 * Financiers.
 * URL : /financier
 *
 * Trois missions : automatisation de la saisie (une pièce budgétaire = une
 * écriture, jamais ressaisie), suivi de la liasse facture → paiement, production
 * des états (journaux, balance, compte administratif) pour la consolidation.
 *
 * « Comptable » étant déjà pris par la comptabilité matières (/comptable), ce
 * module porte l'identifiant « financier ».
 */
class FinancierPanelProvider extends PanelProvider
{
    use HasCommonPanelBootstrap;

    public function register(): void
    {
        parent::register();
        $this->registerPortalLoginRedirect();
    }

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('financier')
            ->path('financier')
            ->login(Login::class)
            ->profile()
            ->colors([
                'primary' => Color::hex('#1d4ed8'),
                'success' => Color::hex('#059669'),
                'danger'  => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info'    => Color::hex('#64748b'),
            ])
            ->favicon(asset('images/favicon.png'))
            ->sidebarCollapsibleOnDesktop()

            ->navigationGroups([
                'Référentiels comptables',
                'Facturation et paiements',
                'Journaux et écritures',
                'Trésorerie et flux',
                'États financiers',
                'Paramétrage',
            ])
            ->navigationItems([
                $this->commonThemeNavigationItem('financier'),
            ])

            ->discoverResources(
                in: app_path('Filament/Financier/Resources'),
                for: 'App\\Filament\\Financier\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Financier/Pages'),
                for: 'App\\Filament\\Financier\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Financier/Widgets'),
                for: 'App\\Filament\\Financier\\Widgets'
            )
            ->plugins($this->commonPlugins())

            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): HtmlString => new HtmlString('
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 me-3 rounded-lg border"
                         style="background:#1d4ed815;border-color:#1d4ed840;">
                        <span class="text-xs font-semibold" style="color:#1d4ed8;">
                            💵 Comptabilité & Flux Financiers
                        </span>
                    </div>
                ')
            )

            ->middleware($this->commonMiddleware())
            ->authMiddleware($this->commonAuthMiddleware('financier'));

        return $this->navbarUniforme($panel, 'financier', 'Comptabilité & Flux Financiers');
    }
}
