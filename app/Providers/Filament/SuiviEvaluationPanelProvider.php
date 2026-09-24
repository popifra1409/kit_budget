<?php
// app/Providers/Filament/SuiviEvaluationPanelProvider.php

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
 * SuiviEvaluationPanelProvider — Module Suivi et Évaluation
 * URL : /suivi-evaluation
 * Documents pivots : Rapports d'activité periodiques (Annexe 9),
 * RAP - Rapport Annuel de Performance (Annexe 10).
 * Alimente en retour l'Etape 1 du cycle CDMT suivant (revue des activites).
 */
class SuiviEvaluationPanelProvider extends PanelProvider
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
            ->id('suivi-evaluation')
            ->path('suivi-evaluation')
            ->login(Login::class)
            ->profile()
            ->colors([
                'primary' => Color::hex('#c2410c'), // orange brique — distinct des autres modules
                'success' => Color::hex('#059669'),
                'danger'  => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info'    => Color::hex('#64748b'),
            ])
            ->brandName('SIGB — Suivi et Évaluation')
            ->favicon(asset('images/favicon.png'))
            ->sidebarCollapsibleOnDesktop()

            ->navigationGroups([
                'Rapports d\'Activité',
                'Rapport Annuel de Performance (RAP)',
                'Tableaux de Bord',
                'Paramétrage',
            ])

            ->discoverResources(
                in: app_path('Filament/SuiviEvaluation/Resources'),
                for: 'App\\Filament\\SuiviEvaluation\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/SuiviEvaluation/Pages'),
                for: 'App\\Filament\\SuiviEvaluation\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/SuiviEvaluation/Widgets'),
                for: 'App\\Filament\\SuiviEvaluation\\Widgets'
            )
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->plugins($this->commonPlugins())
            ->navigationItems([
                $this->commonThemeNavigationItem('suivi-evaluation'),
            ])

            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->renderSwitcher('suivi-evaluation')
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): HtmlString => new HtmlString('
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 me-3 rounded-lg border"
                         style="background:#c2410c15;border-color:#c2410c40;">
                        <span class="text-xs font-semibold" style="color:#c2410c;">
                            📊 Suivi et Évaluation
                        </span>
                    </div>
                ')
            )

            ->middleware($this->commonMiddleware())
            ->authMiddleware($this->commonAuthMiddleware('suivi-evaluation'));
    }
}
