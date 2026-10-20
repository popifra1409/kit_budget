<?php
// app/Providers/Filament/Concerns/HasCommonPanelBootstrap.php

namespace App\Providers\Filament\Concerns;

use App\Models\ParametresFournisseur;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\HtmlString;

trait HasCommonPanelBootstrap
{
    use AvecRetourPortail;

    /**
     * Branding + switcher + recherche globale + raccourcis, identiques sur tous
     * les panels. A appeler en DERNIER de la chaîne de panel(), juste après
     * ->authMiddleware() : les setters Filament modifient le Panel en place.
     *
     * @param string $panelId  id du panel ('comptable', 'suivi-evaluation'…)
     * @param string $module   nom du module dans le fil d'Ariane ('Matières'…)
     */
    protected function navbarUniforme(Panel $panel, string $panelId, string $module): Panel
    {
        return $panel
            ->brandName($this->brandNom($module))
            ->brandLogo($this->brandLogoUrl())
            ->brandLogoHeight('1.75rem')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): HtmlString => $this->renderSwitcher($panelId)
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn(): HtmlString => $this->lienRetourPortail()
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): HtmlString => $this->renderRaccourcis()
            );
    }

    /**
     * Paramètres fournisseur, sans jamais faire échouer le boot d'un panel
     * (installation, table absente, base injoignable).
     */
    protected function fournisseur(): ParametresFournisseur
    {
        static $parametres = [];

        $cle = static::class;

        if (isset($parametres[$cle])) {
            return $parametres[$cle];
        }

        try {
            if (env('INSTALLATION_MODE', false) || !Schema::hasTable('parametres_fournisseur')) {
                throw new \Exception('parametres_fournisseur indisponible');
            }

            $parametres[$cle] = ParametresFournisseur::getOrCreateParametres();
        } catch (\Throwable $e) {
            $parametres[$cle] = new ParametresFournisseur();
        }

        return $parametres[$cle];
    }

    /**
     * Fil d'Ariane : « <nom du logiciel> — <module> », le nom venant de
     * ParametresFournisseur (nom_logiciel), « SIGB » par défaut.
     */
    protected function brandNom(?string $module = null): string
    {
        $nom = trim((string) ($this->fournisseur()->nom_logiciel ?: 'SIGB'));

        return $module === null ? $nom : $nom . ' — ' . $module;
    }

    /**
     * Logo du fournisseur (disque public), ou le logo embarqué s'il existe,
     * sinon null : le composant logo affiche alors le texte seul.
     */
    protected function brandLogoUrl(): ?string
    {
        $logo = $this->fournisseur()->logo_url;

        if (filled($logo)) {
            return str_starts_with($logo, 'http') ? $logo : asset($logo);
        }

        return file_exists(public_path('images/logo.png')) ? asset('images/logo.png') : null;
    }

    /**
     * Raccourci Alt+P → portail. Ctrl/Cmd+K (recherche globale) est déclaré
     * par ->globalSearchKeyBindings() dans navbarUniforme().
     */
    protected function renderRaccourcis(): HtmlString
    {
        $portail = e(url('/portal'));

        return new HtmlString(<<<HTML
            <script>
                window.addEventListener('keydown', function (e) {
                    if (e.altKey && !e.ctrlKey && !e.metaKey && ['p', 'P'].includes(e.key)) {
                        e.preventDefault();
                        window.location.href = '{$portail}';
                    }
                });
            </script>
        HTML);
    }
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
     * Plugin de gestion des thèmes (clair/sombre/personnalisé), identique
     * sur tous les panels.
     */
    protected function commonPlugins(): array
    {
        return [
            \Hasnayeen\Themes\ThemesPlugin::make()
                ->canViewThemesPage(
                    fn() => auth()->check() && (bool) auth()->user()->hasRole(['super_admin', 'admin'])
                ),
        ];
    }

    /**
     * Lien de navigation direct vers la page Thèmes du panel courant.
     * A fusionner avec les navigationItems() propres a chaque module,
     * pas a utiliser seul (sinon ecrase les items existants).
     */
    protected function commonThemeNavigationItem(string $panelId): \Filament\Navigation\NavigationItem
    {
        return \Filament\Navigation\NavigationItem::make('Apparence & Thèmes')
            ->url(fn() => route("filament.{$panelId}.pages.themes"))
            ->icon('heroicon-o-paint-brush')
            ->group('Paramétrage')
            ->sort(99)
            ->visible(fn() => auth()->check() && auth()->user()->hasRole(['super_admin', 'admin']));
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
            \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
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
     * Liste centrale des modules du switcher, dans l'ordre du processus
     * budgétaire. Ajouter une ligne ici suffit à la faire apparaître dans
     * tous les panels.
     *
     * clé = id du panel → [icône, libellé, url, permission d'accès]
     */
    protected function modulesDisponibles(): array
    {
        return [
            'planification'    => ['🎯', 'Planification',      '/planification',    'access_module_planification'],
            'programmation'    => ['📈', 'Programmation',      '/programmation',    'access_module_programmation'],
            'budget'           => ['💰', 'Budget',             '/budget',           'access_module_budget'],
            'marches'          => ['📋', 'Marchés',            '/marches',          'access_module_marches'],
            'comptable'        => ['📦', 'Matières',           '/comptable',        'access_module_comptable'],
            'suivi-evaluation' => ['📊', 'Suivi & Évaluation', '/suivi-evaluation', 'access_module_suivi_evaluation'],
        ];
    }

    /**
     * Switcher de module affiché dans la barre supérieure, avant la recherche
     * globale, avec le lien de retour au portail. Chaque pastille garde son
     * infobulle quand l'écran est trop étroit pour le libellé.
     */
    protected function renderSwitcher(string $active): HtmlString
    {
        $user       = auth()->user();
        $superAdmin = $user?->hasRole('super_admin') ?? false;
        $portail    = e(url('/portal'));

        $html = '<div class="flex items-center gap-2 me-2">';

        $html .= '<a href="' . $portail . '"
                     title="Retour au portail (Alt+P)"
                     class="flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                            text-gray-500 dark:text-gray-400
                            hover:text-gray-700 dark:hover:text-gray-200
                            bg-gray-100 dark:bg-gray-800
                            hover:bg-gray-200 dark:hover:bg-gray-700
                            rounded-lg transition border border-gray-200 dark:border-gray-700 group">
                      <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5"
                           fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                      </svg>
                      <span class="hidden lg:inline">Portail</span>
                  </a>';

        $html .= '<div class="border-l border-gray-300 dark:border-gray-600 h-8 mx-1"></div>';

        $html .= '<div class="flex items-center gap-1 me-1 p-1 rounded-xl
                              bg-gray-100 dark:bg-gray-800
                              border border-gray-200 dark:border-gray-700">';

        foreach ($this->modulesDisponibles() as $key => [$icon, $label, $url, $permission]) {
            $isActive = $key === $active;

            // Même règle d'accès que le portail ; le module courant reste affiché
            if (!$isActive && !$superAdmin && !($user?->can($permission) ?? false)) {
                continue;
            }

            $class = $isActive
                ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold text-gray-900 dark:text-white'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-white/60 dark:hover:bg-gray-700/60';

            $href  = $isActive ? '#' : e($url);

            $html .= '<a href="' . $href . '"
                         title="' . e($label) . '"
                         aria-label="' . e($label) . '"
                         class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition-all ' . $class . '">
                          <span>' . $icon . '</span>
                          <span class="hidden md:inline">' . e($label) . '</span>
                      </a>';
        }

        $html .= '</div></div>';

        return new HtmlString($html);
    }
}
