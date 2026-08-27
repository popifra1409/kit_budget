<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ModulePortal;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;

class PortalPanelProvider extends PanelProvider
{
    /**
     * Surcharge la LoginResponse pour rediriger vers le portail
     * au lieu de chercher filament.admin.pages.dashboard
     */
    public function register(): void
    {
        parent::register();

        $this->app->bind(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            fn() => new class implements \Filament\Http\Responses\Auth\Contracts\LoginResponse {
                public function toResponse($request): \Symfony\Component\HttpFoundation\Response
                {
                    // Fallback si JS ne fonctionne pas
                    return \Illuminate\Support\Facades\Response::make(
                        '<script>window.location.replace("/portal");</script>',
                        200,
                        ['Content-Type' => 'text/html']
                    );
                }
            }
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->default()
            ->login(Login::class)
            ->brandName('SIGB')
            ->favicon(asset('images/favicon.png'))
            ->colors(['primary' => \Filament\Support\Colors\Color::hex('#0ea5e9')])

            // Page unique — le portail de sélection des modules
            ->pages([ModulePortal::class])
            ->widgets([])

            // Pas de sidebar ni de topbar Filament sur le portail
            ->sidebarCollapsibleOnDesktop(false)

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
            ->authMiddleware([Authenticate::class]);
    }
}
