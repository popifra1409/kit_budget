<?php
// app/Providers/Filament/Concerns/HasCommonPanelBootstrap.php

namespace App\Providers\Filament\Concerns;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\HtmlString;

trait HasCommonPanelBootstrap
{
    /**
     * A appeler depuis register(). Redirige apres connexion vers /portal
     * au lieu du dashboard Filament par defaut.
     */
    protected function registerPortalLoginRedirect(): void
    {
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

    /**
     * Pile de middleware standard, identique sur tous les panels module.
     */
    protected function commonMiddleware(): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];
    }

    /**
     * authMiddleware standard avec le controle d'acces par module
     * (middleware 'module.access:{cle}' deja existant dans l'app).
     */
    protected function commonAuthMiddleware(string $moduleKey): array
    {
        return [
            Authenticate::class,
            "module.access:{$moduleKey}",
        ];
    }

    /**
     * Liste centrale des modules affiches dans le switcher.
     * Ajouter une ligne ici suffit a la faire apparaitre partout ou
     * ce trait est utilise.
     */
    protected function modulesDisponibles(): array
    {
        return [
            'planification' => ['🎯', 'Planification', '/planification'],
            'budget'        => ['💰', 'Budget',         '/budget'],
            'comptable'     => ['📦', 'Matières',       '/comptable'],
            'marches'       => ['📋', 'Marchés',        '/marches'],
        ];
    }

    /**
     * Switcher de module affiche dans le header (avant la barre de recherche globale),
     * avec un lien de retour vers le portail.
     */
    protected function renderSwitcher(string $active): HtmlString
    {
        $modules = $this->modulesDisponibles();

        $html = '<div class="flex items-center gap-1 me-3 p-1 rounded-xl
                             bg-gray-100 dark:bg-gray-800
                             border border-gray-200 dark:border-gray-700">';

        $html .= '<a href="/portal"
                     class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all
                            text-gray-500 dark:text-gray-400
                            hover:text-gray-700 dark:hover:text-gray-200
                            hover:bg-white/60 dark:hover:bg-gray-700/60"
                     title="Retour au portail">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                      </svg>
                      <span class="hidden lg:inline">Portail</span>
                  </a>';

        $html .= '<div class="border-l border-gray-300 dark:border-gray-600 h-6 mx-1"></div>';

        foreach ($modules as $key => [$icon, $label, $url]) {
            $isActive = $key === $active;
            $class = $isActive
                ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold text-gray-900 dark:text-white'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 hover:bg-white/60 dark:hover:bg-gray-700/60';

            $html .= '<a href="' . ($isActive ? '#' : $url) . '"
                         class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition-all ' . $class . '">
                          <span>' . $icon . '</span>
                          <span class="hidden md:inline">' . $label . '</span>
                      </a>';
        }

        return new HtmlString($html . '</div>');
    }
}
