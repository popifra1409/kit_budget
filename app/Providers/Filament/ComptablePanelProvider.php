<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Widgets;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use App\Filament\Pages\Auth\Login;
use Hasnayeen\Themes\ThemesPlugin;
use Filament\Navigation\NavigationItem;

/**
 * ComptablePanelProvider — Module Comptabilité Matières
 * URL : /comptable
 * Cycle complet : Acquisition → Affectation → Suivi → Entretien → Aliénation
 */
class ComptablePanelProvider extends PanelProvider
{
    use Concerns\HasCommonPanelBootstrap;

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
        $panel = $panel
            ->id('comptable')
            ->path('comptable')
            ->login(Login::class)
            ->profile()
            ->colors([
                'primary' => Color::hex('#059669'),
                'success' => Color::hex('#16a34a'),
                'danger'  => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info'    => Color::hex('#64748b'),
            ])
            ->favicon(asset('images/favicon.png'))
            ->sidebarCollapsibleOnDesktop()

            ->navigationGroups([
                'Référentiels',
                'Acquisition des biens',
                'Affectation & Mise en service',
                'Suivi & Inventaire',
                'Entretien & Réparation',
                'Amortissement',
                'Aliénation',
                'Rapports & États',
                'Paramétrage',
            ])
            ->navigationItems([
                NavigationItem::make('Apparence & Thèmes')
                    ->url(fn() => route('filament.comptable.pages.themes'))
                    ->icon('heroicon-o-paint-brush')
                    ->group('Paramétrage')
                    ->sort(99)
                    ->visible(fn() => auth()->check() && auth()->user()->hasRole(['super_admin', 'admin'])),
            ])

            ->discoverResources(
                in: app_path('Filament/Comptable/Resources'),
                for: 'App\\Filament\\Comptable\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Comptable/Pages'),
                for: 'App\\Filament\\Comptable\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Comptable/Widgets'),
                for: 'App\\Filament\\Comptable\\Widgets'
            )
            ->widgets([
                \App\Filament\Comptable\Widgets\StatsComptableWidget::class,
                \App\Filament\Comptable\Widgets\AlertesStockWidget::class,
                \App\Filament\Comptable\Widgets\PipelineExpressionsBesoinWidget::class,
                \App\Filament\Comptable\Widgets\ActionsRequisesWidget::class,
                \App\Filament\Comptable\Widgets\MouvementsStockWidget::class,
                \App\Filament\Comptable\Widgets\TopArticlesConsommesWidget::class,
            ])
            ->plugins([
                ThemesPlugin::make()
                    ->canViewThemesPage(
                        fn() => auth()->check() && (bool) auth()->user()->hasRole(['super_admin', 'admin'])
                    ),
            ])

            // Badge du module, avant le menu utilisateur (switcher et retour
            // au portail sont gérés par navbarUniforme() dans le trait commun)
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): HtmlString => new HtmlString('
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 me-3 rounded-lg border"
                         style="background:#05966915;border-color:#05966940;">
                        <span class="text-xs font-semibold" style="color:#059669;">
                            📦 Comptabilité Matières
                        </span>
                    </div>
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
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'module.access:comptable',
            ]);

        return $this->navbarUniforme($panel, 'comptable', 'Comptabilité Matières');
    }
}
