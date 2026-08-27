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

class MarchesPanelProvider extends PanelProvider
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
        return $panel
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
            ->brandName('SIGB — Marchés')
            ->favicon(asset('images/favicon.png'))
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Planification',
                'Appels d\'Offres',
                'Évaluation & Attribution',
                'Contrats & Avenants',
                'Suivi d\'Exécution',
                'Paiements Marchés',
                'Paramétrage Marchés',
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
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->renderSwitcher('marches')
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
                'module.access:marches',
            ]);
    }

    private function renderSwitcher(string $active): HtmlString
    {
        $modules = [
            'budget'         => ['💰', 'Budget',        '/budget'],
            'comptable'      => ['📦', 'Matières',      '/comptable'],
            'marches'        => ['📋', 'Marchés',       '/marches'],
            'planification'  => ['🎯', 'Planification', '/planification'],
        ];

        $html  = '<div class="flex items-center gap-2 me-2">';
        $html .= '<a href="/portal"
                 class="flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                        text-gray-500 dark:text-gray-400
                        hover:text-gray-700 dark:hover:text-gray-200
                        bg-gray-100 dark:bg-gray-800
                        hover:bg-gray-200 dark:hover:bg-gray-700
                        rounded-lg transition border border-gray-200 dark:border-gray-700 group"
                 title="Retour au portail">
                  <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                  </svg>
                  <span class="hidden lg:inline">Portail</span>
              </a>';
        $html .= '<div class="border-l border-gray-300 dark:border-gray-600 h-8 mx-1"></div>';

        $html = '<div class="flex items-center gap-1 me-3 p-1 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">';
        foreach ($modules as $key => [$icon, $label, $url]) {
            $isActive = $key === $active;
            $class = $isActive
                ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold text-gray-900 dark:text-white'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 hover:bg-white/60 dark:hover:bg-gray-700/60';
            $html .= '<a href="' . ($isActive ? '#' : $url) . '" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition-all ' . $class . '">'
                . '<span>' . $icon . '</span><span class="hidden md:inline">' . $label . '</span></a>';
        }
        return new HtmlString($html . '</div>');
    }
}
