<?php
// app/Providers/Filament/PlanificationPanelProvider.php

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
 * PlanificationPanelProvider — Module Planification Stratégique
 * URL : /planification
 * Chaine : CSP Ministere de la Sante -> Plan Strategique EP -> Sous-Programmes -> Activites
 */
class PlanificationPanelProvider extends PanelProvider
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
            ->id('planification')
            ->path('planification')
            ->login(Login::class)
            ->profile()
            ->colors([
                'primary' => Color::hex('#028090'),
                'success' => Color::hex('#059669'),
                'danger'  => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info'    => Color::hex('#64748b'),
            ])
            ->brandName('SIGB — Planification')
            ->favicon(asset('images/favicon.png'))
            ->sidebarCollapsibleOnDesktop()

            ->navigationGroups([
                'Cadrage Stratégique',
                'Plans Stratégiques EP',
                'Activités',
                'Paramétrage Planification',
            ])

            ->discoverResources(
                in: app_path('Filament/Planification/Resources'),
                for: 'App\\Filament\\Planification\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Planification/Pages'),
                for: 'App\\Filament\\Planification\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Planification/Widgets'),
                for: 'App\\Filament\\Planification\\Widgets'
            )
            ->widgets([
                Widgets\AccountWidget::class,
            ])

            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->renderSwitcher('planification')
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): HtmlString => new HtmlString('
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 me-3 rounded-lg border"
                         style="background:#02809015;border-color:#02809040;">
                        <span class="text-xs font-semibold" style="color:#028090;">
                            🎯 Planification Stratégique
                        </span>
                    </div>
                ')
            )

            ->middleware($this->commonMiddleware())
            ->authMiddleware($this->commonAuthMiddleware('planification'));
    }
}
