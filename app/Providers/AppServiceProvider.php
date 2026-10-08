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
use App\Observers\DecisionAdministrativeObserver;
use App\Http\Responses\CustomLogoutResponse;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse;
use App\Models\BonCommande;
use App\Models\DecisionAdministrative;
use App\Observers\BonCommandeObserver;
use App\Models\PieceDossier;
use App\Observers\PieceDossierObserver;
use App\Models\DepenseRegie;
use App\Observers\DepenseRegieObserver;
use App\Models\BonCommandeRegie;
use App\Observers\BonCommandeRegieObserver;
use App\Models\LigneBudgetaire;
use App\Observers\LigneBudgetaireConcordanceObserver;
use App\Support\Audit;
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
        /*
        |------------------------------------------------------------------
        | MORPH MAP (imposee)
        |------------------------------------------------------------------
        | enforceMorphMap : toute relation polymorphe vers un modele ABSENT de
        | cette liste leve une ClassMorphViolationException. Le journal d'audit
        | rattachant chaque entree a son document par une relation polymorphe,
        | TOUT modele audite (config/audit.php) doit figurer ici.
        | Controle : php artisan audit:couverture
        |
        | ⚠️ Ne jamais renommer ni supprimer un alias existant : il est stocke en
        |    base (activity_log, engagements, transmissions...). Seuls les ajouts
        |    sont sans risque.
        */
        Relation::enforceMorphMap([
            //configurations
            'programme' => \App\Models\Programme::class,
            'action' => \App\Models\Action::class,
            'activite' => \App\Models\Activite::class,
            'tache' => \App\Models\Tache::class,
            'nomenclature_budgetaire' => \App\Models\NomenclatureBudgetaire::class,
            'exercice' => \App\Models\Exercice::class,
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
            'parametre_execution'          => \App\Models\ParametreExecution::class,
            // Documents
            'bon_commande' => \App\Models\BonCommande::class,
            'engagement'   => \App\Models\Engagement::class,
            'ordonnance_paiement'     => \App\Models\OrdonnancePaiement::class,
            'ordonnance_paiement_impot' => \App\Models\OrdonnancePaiement::class,
            'decision_administrative' => \App\Models\DecisionAdministrative::class,
            'App\Models\DecisionAdministrative' => \App\Models\DecisionAdministrative::class,
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
            //regie d'avance et menus dépenses
            'regie_avance'              => \App\Models\RegieAvance::class,
            'bon_commande_regie'        => \App\Models\BonCommandeRegie::class,
            'depense_regie'             => \App\Models\DepenseRegie::class,
            'provision_ligne_regie'     => \App\Models\ProvisionLigneRegie::class,

            // Module Planification Stratégique
            'csp_ministere_sante'    => \App\Models\CspMinistereSante::class,
            'plan_strategique_ep'    => \App\Models\PlanStrategiqueEp::class,
            'sous_programme_ep'      => \App\Models\SousProgrammeEp::class,

            //Module Programmation
            'ppa_exercice' => \App\Models\PpaExercice::class,
            'cbmt_exercice' => \App\Models\CbmtExercice::class,
            'cdmt_exercice' => \App\Models\CdmtExercice::class,
            'extrant' => \App\Models\Extrant::class,

            //Suivi et evaluation
            'rapport_activite_periodique' => \App\Models\RapportActivitePeriodique::class,
            'rapport_annuel_performance' => \App\Models\RapportAnnuelPerformance::class,

            // ─────────────────────────────────────────────────────────────
            // ✅ AJOUTS — modeles desormais audites (config/audit.php).
            //    Sans ces alias, l'audit de ces modeles echouerait en silence.
            // ─────────────────────────────────────────────────────────────

            // Budget : lignes, avenants, collectifs, recettes, regies, parametres
            'ligne_budgetaire'             => \App\Models\LigneBudgetaire::class,
            'groupe_nomenclature'          => \App\Models\GroupeNomenclature::class,
            'ligne_engagement'             => \App\Models\LigneEngagement::class,
            'avenant'                      => \App\Models\Avenant::class,
            'ligne_bon_commande'           => \App\Models\LigneBonCommande::class,
            'menu_depense_decision'        => \App\Models\MenuDepenseDecision::class,
            'bordereau_engagement_ligne'   => \App\Models\BordereauEngagementLigne::class,
            'ligne_memoire_depense'        => \App\Models\LigneMemoireDepense::class,
            'facture_proforma'             => \App\Models\FactureProforma::class,
            'ligne_facture_proforma'       => \App\Models\LigneFactureProforma::class,
            'collectif_budgetaire'         => \App\Models\CollectifBudgetaire::class,
            'mouvement_collectif'          => \App\Models\MouvementCollectif::class,
            'ligne_prevision_recette'      => \App\Models\LignePrevisionRecette::class,
            'prevision_recette_mensuelle'  => \App\Models\PrevisionRecetteMensuelle::class,
            'prevision_budget_programme'   => \App\Models\PrevisionBudgetProgramme::class,
            'ligne_regie_avance'           => \App\Models\LigneRegieAvance::class,
            'ligne_bon_commande_regie'     => \App\Models\LigneBonCommandeRegie::class,
            'decaissement_regie'           => \App\Models\DecaissementRegie::class,
            'ligne_depense_regie'          => \App\Models\LigneDepenseRegie::class,
            'provision_consommation'       => \App\Models\ProvisionConsommation::class,
            'mode_paiement'                => \App\Models\ModePaiement::class,
            'taux_ir'                      => \App\Models\TauxIr::class,
            'regime_fiscal'                => \App\Models\RegimeFiscal::class,

            // Comptabilité matières
            'article'                      => \App\Models\Article::class,
            'categorie_article'            => \App\Models\CategorieArticle::class,
            'unite_mesure'                 => \App\Models\UniteMesure::class,
            'conditionnement'              => \App\Models\Conditionnement::class,
            'stock'                        => \App\Models\Stock::class,
            'fiche_stock'                  => \App\Models\FicheStock::class,
            'expression_besoin'            => \App\Models\ExpressionBesoin::class,
            'ligne_expression_besoin'      => \App\Models\LigneExpressionBesoin::class,
            'fiche_consolidation_besoin'   => \App\Models\FicheConsolidationBesoin::class,
            'reception'                    => \App\Models\Reception::class,
            'ligne_reception'              => \App\Models\LigneReception::class,
            'ordre_entree'                 => \App\Models\OrdreEntree::class,
            'bordereau_mouvement'          => \App\Models\BordereauMouvement::class,
            'mouvement_bordereau'          => \App\Models\MouvementBordereau::class,

            // Marchés publics
            'piece_dossier'                => \App\Models\PieceDossier::class,

            // Planification stratégique
            'cadre_logique'                => \App\Models\CadreLogique::class,
            'objectif_principal'           => \App\Models\ObjectifPrincipal::class,
            'objectif_specifique'          => \App\Models\ObjectifSpecifique::class,
            'indicateur'                   => \App\Models\Indicateur::class,
            'valeur_indicateur'            => \App\Models\ValeurIndicateur::class,

            // Programmation
            'cbmt_ligne'                   => \App\Models\CbmtLigne::class,
            'cdmt_ligne'                   => \App\Models\CdmtLigne::class,

            // Suivi et évaluation
            'rapport_activite_ligne'       => \App\Models\RapportActiviteLigne::class,
            'cloture_exercice'             => \App\Models\ClotureExercice::class,
            'cloture_ligne'                => \App\Models\ClotureLigne::class,
            'tiers_recette'                => \App\Models\TiersRecette::class,

            // Administration
            'transmission'                 => \App\Models\Transmission::class,

            //liquidation et service fait
            'nature_service_fait'  => \App\Models\NatureServiceFait::class,
            'preuve_service_fait'  => \App\Models\PreuveServiceFait::class,
            'liquidation'          => \App\Models\Liquidation::class,
            'liquidation_preuve'   => \App\Models\LiquidationPreuve::class,

            'paiement_exceptionnel' => \App\Models\PaiementExceptionnel::class,
        ]);

        // Modele de role propre a l'application (s'il existe et differe du modele Spatie) :
        // alias distinct pour ne pas modifier l'alias 'role' deja stocke en base.
        if (class_exists(\App\Models\Role::class) && \App\Models\Role::class !== Role::class) {
            Relation::morphMap(['role_application' => \App\Models\Role::class]);
        }

        DecisionAdministrative::observe(DecisionAdministrativeObserver::class);
        LignePrevisionRecette::observe(LignePrevisionRecetteObserver::class);
        // Enregistrer l'observer BonCommande
        BonCommande::observe(BonCommandeObserver::class);
        PieceDossier::observe(PieceDossierObserver::class);
        DepenseRegie::observe(DepenseRegieObserver::class);
        BonCommandeRegie::observe(BonCommandeRegieObserver::class);
        LigneBudgetaire::observe(LigneBudgetaireConcordanceObserver::class);

        // ✅ AJOUT — Audit de tous les modules (modeles listes dans config/audit.php,
        //    hors modeles deja journalises nativement par LogsActivity)
        Audit::enregistrerObservateurs();

        // Enregistrer le CSS personnalisé
        FilamentAsset::register([
            Css::make('custom-theme', resource_path('css/filament/admin/theme.css')),
        ]);

        \Livewire\Livewire::component(
            'agent-budgetaire-widget',
            \App\Filament\Budget\Widgets\AgentBudgetaireWidget::class
        );
    }
}
