<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Providers\Filament\Concerns\HasCommonPanelBootstrap;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Support\HtmlString;

/**
 * ProgrammationPanelProvider — Module Programmation
 * URL : /programmation
 * Document pivot : PPA (Programme de Performance Annuel), Tableau 11
 * du guide d'arrimage des EP.
 */
class ProgrammationPanelProvider extends PanelProvider
{
    use HasCommonPanelBootstrap;

    public function register(): void
    {
        parent::register();
        $this->registerPortalLoginRedirect();
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('programmation')
            ->path('programmation')
            ->login(Login::class)
            ->profile()
            ->colors([
                'primary' => Color::hex('#7c3aed'), // violet — distinct de Planification (teal)
                'success' => Color::hex('#059669'),
                'danger'  => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info'    => Color::hex('#64748b'),
            ])
            ->brandName('SIGB — Programmation')
            ->favicon(asset('images/favicon.png'))
            ->sidebarCollapsibleOnDesktop()

            ->navigationGroups([
                'Programme de Performance Annuel',
                'Rapports',
                'Paramétrage',
            ])

            ->discoverResources(
                in: app_path('Filament/Programmation/Resources'),
                for: 'App\\Filament\\Programmation\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Programmation/Pages'),
                for: 'App\\Filament\\Programmation\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Programmation/Widgets'),
                for: 'App\\Filament\\Programmation\\Widgets'
            )
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->plugins($this->commonPlugins())
            ->navigationItems([
                $this->commonThemeNavigationItem('programmation'),
            ])

            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->renderSwitcher('programmation')
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): HtmlString => new HtmlString('
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 me-3 rounded-lg border"
                         style="background:#7c3aed15;border-color:#7c3aed40;">
                        <span class="text-xs font-semibold" style="color:#7c3aed;">
                            📈 Programmation
                        </span>
                    </div>
                ')
            )

            ->middleware($this->commonMiddleware())
            ->authMiddleware($this->commonAuthMiddleware('programmation'));
    }
}
