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
 * MarchesPanelProvider — Module Marchés publics
 * URL : /marches
 */
class MarchesPanelProvider extends PanelProvider
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
            ->id('marches')
            ->path('marches')
            ->login(Login::class)
            ->profile()
            ->colors([
                'primary' => Color::hex('#7c3aed'),
                'success' => Color::hex('#059669'),
                'danger'  => Color::hex('#dc2626'),
                'warning' => Color::hex('#f59e0b'),
                'info'    => Color::hex('#64748b'),
            ])
            ->favicon(asset('images/favicon.png'))
            ->sidebarCollapsibleOnDesktop()

            ->navigationGroups([
                'Planification',
                'Appels d\'Offres',
                'Évaluation & Attribution',
                'Contrats & Avenants',
                'Suivi d\'Exécution',
                'Paiements Marchés',
                'Paramétrage',
            ])
            ->navigationItems([
                NavigationItem::make('Apparence & Thèmes')
                    ->url(fn() => route('filament.marches.pages.themes'))
                    ->icon('heroicon-o-paint-brush')
                    ->group('Paramétrage')
                    ->sort(99)
                    ->visible(fn() => auth()->check() && auth()->user()->hasRole(['super_admin', 'admin'])),
            ])

            ->discoverResources(
                in: app_path('Filament/Marches/Resources'),
                for: 'App\\Filament\\Marches\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Marches/Pages'),
                for: 'App\\Filament\\Marches\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Marches/Widgets'),
                for: 'App\\Filament\\Marches\\Widgets'
            )
            ->widgets([Widgets\AccountWidget::class])
            ->plugins([
                ThemesPlugin::make()
                    ->canViewThemesPage(
                        fn() => auth()->check() && (bool) auth()->user()->hasRole(['super_admin', 'admin'])
                    ),
            ])

            // Badge et switcher : le retour au portail et le sélecteur de
            // modules viennent de navbarUniforme() (trait commun)

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
                'module.access:marches',
            ]);

        return $this->navbarUniforme($panel, 'marches', 'Marchés');
    }
}
