<?php

namespace App\Providers;

use Livewire\Livewire;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use App\Models\LignePrevisionRecette;
use App\Observers\LignePrevisionRecetteObserver;
use App\Http\Responses\CustomLogoutResponse;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse;
use App\Models\BonCommande;
use App\Observers\BonCommandeObserver;
use App\Models\PieceDossier;
use App\Observers\PieceDossierObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Personnaliser la réponse de déconnexion
        $this->app->bind(
            LogoutResponse::class,
            CustomLogoutResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LignePrevisionRecette::observe(LignePrevisionRecetteObserver::class);
        // Enregistrer l'observer BonCommande
        BonCommande::observe(BonCommandeObserver::class);
        PieceDossier::observe(PieceDossierObserver::class);
        // Enregistrer le CSS personnalisé
        FilamentAsset::register([
            Css::make('custom-theme', resource_path('css/filament/admin/theme.css')),
        ]);
    }
}
