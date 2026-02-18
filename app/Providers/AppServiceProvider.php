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
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
        // ✅ Mapper les types courts vers les classes complètes
        Relation::enforceMorphMap([
            //configurations
            'programme' => \App\Models\Programme::class,
            'action' => \App\Models\Action::class,
            'activite' => \App\Models\Activite::class,
            'tache' => \App\Models\Tache::class,
            'nomenclature_budgetaire' => \App\Models\NomenclatureBudgetaire::class,
            'budget' => \App\Models\Budget::class,
            'prevision_recette' => \App\Models\PrevisionRecette::class,
            'recette_reelle' => \App\Models\RecetteReelle::class,
            'virement_budgetaire' => \App\Models\VirementBudgetaire::class,
            'memoire_depense' => \App\Models\MemoireDepense::class,
            'reference_mercuriale' => \App\Models\ReferenceMercuriale::class,
            'etat_config' => \App\Models\EtatConfig::class,
            'type_decision' => \App\Models\TypeDecision::class,
            'type_engagement' => \App\Models\TypeEngagement::class,
            'parametre_structure' => \App\Models\ParametresStructure::class,
            'parametre_fournisseur' => \App\Models\ParametresFournisseur::class,
            // Documents
            'bon_commande' => \App\Models\BonCommande::class,
            'engagement'   => \App\Models\Engagement::class,
            'ordonnance_paiement'     => \App\Models\OrdonnancePaiement::class,
            'ordonnance_paiement_impot' => \App\Models\OrdonnancePaiement::class,
            'decision_administrative' => \App\Models\DecisionAdministrative::class,
            'dossier_fournisseur' => \App\Models\DossierFournisseur::class,
            'bordereau_engagement' => \App\Models\BordereauEngagement::class,
            //Acteurs
            'fournisseur' => \App\Models\Fournisseur::class,
            'personnel' => \App\Models\Personnel::class,
            'service' => \App\Models\Service::class,
            'App\Models\User' => \App\Models\User::class,
            'user'            => \App\Models\User::class,
            // 🔐 Sécurité (Spatie)
            'role'       => Role::class,
            'permission' => Permission::class,

        ]);

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
