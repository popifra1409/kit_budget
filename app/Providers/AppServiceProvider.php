<?php

namespace App\Providers;

use Livewire\Livewire;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // // Enregistrer le composant Livewire
        // Livewire::component('topbar-actions', \App\Livewire\CustomTopbar::class);

        // // Enregistrer les assets pour le toggle hide/show
        // FilamentAsset::register([
        //     Css::make('sidebar-toggle-css', resource_path('css/filament/sidebar-toggle-hide-show.css')),
        //     Js::make('sidebar-toggle-js', resource_path('js/filament/sidebar-toggle-hide-show.js')),
        // ]);

        // // Injecter les quick actions dans le topbar (début)
        // FilamentView::registerRenderHook(
        //     'panels::topbar.start',
        //     fn(): string => Blade::render('<livewire:topbar-actions />')
        // );
    }
}
