<?php

namespace App\Providers\Filament\Concerns;

use Illuminate\Support\HtmlString;

/**
 * Lien « Retour au portail » affiché EN TÊTE du menu latéral.
 *
 * ⚠️ Ce n'est volontairement PAS un NavigationItem : Filament redirige la racine
 *    d'un panel (/comptable, /admin...) vers le PREMIER élément de navigation.
 *    Un NavigationItem « Retour au portail » placé en premier renvoyait donc au
 *    portail dès l'ouverture du module (->homeUrl() ne modifie que le logo).
 *
 *    Le lien est injecté par l'emplacement SIDEBAR_NAV_START, au-dessus des groupes :
 *    visuellement en premier, sans influence sur la page d'accueil du panel.
 *
 * Utilisation dans un PanelProvider :
 *     use Concerns\AvecRetourPortail;
 *     ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => $this->lienRetourPortail())
 */
trait AvecRetourPortail
{
    protected function lienRetourPortail(): HtmlString
    {
        $url = e(url('/portal'));

        return new HtmlString(<<<HTML
            <div class="px-2 pb-2 mb-2 border-b border-gray-200 dark:border-white/10">
                <a href="{$url}"
                   title="Retour au portail"
                   class="flex items-center gap-x-3 rounded-lg px-2 py-2 text-sm font-medium
                          text-gray-700 dark:text-gray-200
                          hover:bg-gray-100 dark:hover:bg-white/5 transition">
                    <svg class="h-6 w-6 shrink-0 text-gray-400 dark:text-gray-500"
                         fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                    </svg>
                    <span x-show="\$store.sidebar.isOpen" x-transition:enter.delay.100ms>Retour au portail</span>
                </a>
            </div>
        HTML);
    }
}
