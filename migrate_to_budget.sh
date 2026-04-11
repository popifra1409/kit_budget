#!/bin/bash
# ================================================================
# migrate_to_budget.sh
# Migration complète vers app/Filament/Budget
# Exécuter à la racine du projet Laravel :
#   bash migrate_to_budget.sh
# ================================================================

set -e  # Stopper à la première erreur

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log()    { echo -e "${GREEN}✅ $1${NC}"; }
warn()   { echo -e "${YELLOW}⚠️  $1${NC}"; }
error()  { echo -e "${RED}❌ $1${NC}"; exit 1; }
info()   { echo -e "${BLUE}ℹ️  $1${NC}"; }
header() { echo -e "\n${BLUE}══════════════════════════════════════${NC}"; echo -e "${BLUE}  $1${NC}"; echo -e "${BLUE}══════════════════════════════════════${NC}"; }

# Vérifier qu'on est bien à la racine du projet
[ -f "artisan" ] || error "Lancez ce script depuis la racine du projet Laravel (où se trouve artisan)"

# ================================================================
# ÉTAPE 0 — SAUVEGARDE GIT OBLIGATOIRE
# ================================================================
header "ÉTAPE 0 — Sauvegarde git"

if ! git diff-index --quiet HEAD -- 2>/dev/null; then
    warn "Vous avez des modifications non commitées."
    read -p "Continuer quand même ? (o/N) : " confirm
    [[ "$confirm" =~ ^[oO]$ ]] || error "Migration annulée. Commitez d'abord vos changements."
fi

git add -A && git commit -m "chore: avant migration vers Filament/Budget" --allow-empty
log "Commit de sauvegarde créé"

# ================================================================
# ÉTAPE 1 — CRÉER L'ARBORESCENCE
# ================================================================
header "ÉTAPE 1 — Création de l'arborescence"

mkdir -p app/Filament/Budget/Resources
mkdir -p app/Filament/Budget/Pages
mkdir -p app/Filament/Budget/Widgets
log "Dossiers créés : app/Filament/Budget/{Resources,Pages,Widgets}"

# ================================================================
# ÉTAPE 2 — DÉPLACER LES RESOURCES DU MODULE BUDGET
# ================================================================
header "ÉTAPE 2 — Déplacement des Resources Budget"

# Liste des resources à déplacer vers le module Budget
BUDGET_RESOURCES=(
    "ActionResource"
    "ActiviteResource"
    "BonCommandeResource"
    "BordereauEngagementResource"
    "BudgetResource"
    "DecisionAdministrativeResource"
    "DossierFournisseurResource"
    "EngagementResource"
    "EtatConfigResource"
    "ExerciceResource"
    "FicheControleEngagementsResource"
    "FournisseurResource"
    "MemoireDepenseResource"
    "NomenclatureBudgetaireResource"
    "OrdonnancePaiementResource"
    "ParametresFournisseurResource"
    "ParametresStructureResource"
    "PrevisionRecetteResource"
    "ProgrammeResource"
    "RecetteReelleResource"
    "ReferenceMercurialeResource"
    "TacheResource"
    "TypeDecisionResource"
    "TypeEngagementResource"
    "VirementBudgetaireResource"
)

# Resources qui RESTENT dans app/Filament/Resources (panel admin partagé)
# ActivityResource, PermissionResource, PersonnelResource,
# RoleResource, ServiceResource, UserResource → NE SONT PAS déplacées

for resource in "${BUDGET_RESOURCES[@]}"; do
    # Déplacer le fichier principal
    if [ -f "app/Filament/Resources/${resource}.php" ]; then
        mv "app/Filament/Resources/${resource}.php" \
           "app/Filament/Budget/Resources/${resource}.php"
        log "Déplacé : ${resource}.php"
    else
        warn "Introuvable : ${resource}.php (déjà déplacé ?)"
    fi

    # Déplacer le dossier de pages/relation managers
    if [ -d "app/Filament/Resources/${resource}" ]; then
        mv "app/Filament/Resources/${resource}" \
           "app/Filament/Budget/Resources/${resource}"
        log "Déplacé : ${resource}/"
    fi
done

# ================================================================
# ÉTAPE 3 — DÉPLACER LES WIDGETS BUDGET
# ================================================================
header "ÉTAPE 3 — Déplacement des Widgets Budget"

BUDGET_WIDGETS=(
    "ActivitesRecentesWidget"
    "AlertesWidget"
    "BudgetOverviewWidget"
    "CacheManagementWidget"
    "ChartRecettesMensuelles"
    "EngagementsParTypeWidget"
    "EvolutionMensuelleWidget"
    "ExerciceActifWidget"
    "GraphiqueEvolution"
    "MesTachesEnAttenteWidget"
    "RecettesChart"
    "RecettesStats"
    "StatistiquesTransmissionsWidget"
    "StatsMultiExercicesWidget"
    "StatsRecettesOverview"
    "StatsRecettesWidget"
    "TableRecettesMensuelles"
    "TableTopRecettes"
    "TauxRealisationWidget"
    "TopServicesWidget"
    "ToutesLesTransmissionsWidget"
)

# Widgets qui RESTENT partagés (utilisés par plusieurs panels)
# WelcomeWidget, ChangerMotDePasseWidget → restent dans app/Filament/Widgets

for widget in "${BUDGET_WIDGETS[@]}"; do
    if [ -f "app/Filament/Widgets/${widget}.php" ]; then
        mv "app/Filament/Widgets/${widget}.php" \
           "app/Filament/Budget/Widgets/${widget}.php"
        log "Widget déplacé : ${widget}.php"
    else
        warn "Widget introuvable : ${widget}.php"
    fi
done

# ================================================================
# ÉTAPE 4 — MISE À JOUR DES NAMESPACES
# ================================================================
header "ÉTAPE 4 — Mise à jour des namespaces"

info "Correction des namespaces dans app/Filament/Budget/ ..."

# 4a. Fichiers principaux des Resources
# namespace App\Filament\Resources; → namespace App\Filament\Budget\Resources;
find app/Filament/Budget/Resources -maxdepth 1 -name "*.php" | while read file; do
    sed -i \
        's/^namespace App\\Filament\\Resources;/namespace App\\Filament\\Budget\\Resources;/' \
        "$file"
    log "Namespace corrigé : $file"
done

# 4b. Fichiers des sous-dossiers Pages, RelationManagers, Widgets internes
# namespace App\Filament\Resources\XxxResource\Pages;
# → namespace App\Filament\Budget\Resources\XxxResource\Pages;
find app/Filament/Budget/Resources -mindepth 2 -name "*.php" | while read file; do
    sed -i \
        's/namespace App\\Filament\\Resources\\/namespace App\\Filament\\Budget\\Resources\\/g' \
        "$file"
done
log "Namespaces des sous-dossiers corrigés"

# 4c. Widgets déplacés
find app/Filament/Budget/Widgets -name "*.php" | while read file; do
    sed -i \
        's/^namespace App\\Filament\\Widgets;/namespace App\\Filament\\Budget\\Widgets;/' \
        "$file"
done
log "Namespaces widgets corrigés"

# 4d. Corriger les use statements dans les fichiers Budget
# (les resources qui s'importent entre elles)
find app/Filament/Budget -name "*.php" | while read file; do
    # use App\Filament\Resources\XxxResource\Pages → use App\Filament\Budget\Resources\...
    sed -i \
        's/use App\\Filament\\Resources\\/use App\\Filament\\Budget\\Resources\\/g' \
        "$file"

    # use App\Filament\Widgets\XxxWidget → use App\Filament\Budget\Widgets\XxxWidget
    # (uniquement pour les widgets qui ont été déplacés)
    for widget in "${BUDGET_WIDGETS[@]}"; do
        sed -i \
            "s/use App\\\\Filament\\\\Widgets\\\\${widget};/use App\\\\Filament\\\\Budget\\\\Widgets\\\\${widget};/g" \
            "$file"
    done
done
log "Use statements corrigés dans Budget/"

# ================================================================
# ÉTAPE 5 — CORRIGER LES RÉFÉRENCES DEPUIS LES AUTRES FICHIERS
# ================================================================
header "ÉTAPE 5 — Correction des références externes"

info "Correction des références dans app/ (hors Budget/) ..."

# Les fichiers dans app/Filament/Resources (admin) ou app/ peuvent
# référencer des resources Budget → mettre à jour

for resource in "${BUDGET_RESOURCES[@]}"; do
    # Chercher et corriger dans tout le projet (hors Budget/ lui-même)
    find app -name "*.php" \
        ! -path "app/Filament/Budget/*" | while read file; do

        # use App\Filament\Resources\XxxResource; → use App\Filament\Budget\Resources\XxxResource;
        sed -i \
            "s/use App\\\\Filament\\\\Resources\\\\${resource};/use App\\\\Filament\\\\Budget\\\\Resources\\\\${resource};/g" \
            "$file"

        # use App\Filament\Resources\XxxResource\Pages\ → use App\Filament\Budget\Resources\XxxResource\Pages\
        sed -i \
            "s/use App\\\\Filament\\\\Resources\\\\${resource}\\\\/use App\\\\Filament\\\\Budget\\\\Resources\\\\${resource}\\\\/g" \
            "$file"
    done
done
log "Références externes corrigées"

# ================================================================
# ÉTAPE 6 — VÉRIFICATION DES VUES BLADE
# ================================================================
header "ÉTAPE 6 — Vérification vues Blade"

info "Les vues Blade ne bougent PAS. Vérification des chemins hard-codés..."

# Chercher des références hard-codées à des classes déplacées dans les vues
BLADE_ISSUES=$(grep -r "App\\\\Filament\\\\Resources\\" resources/views/ 2>/dev/null || true)
if [ -n "$BLADE_ISSUES" ]; then
    warn "Références trouvées dans les vues (à vérifier manuellement) :"
    echo "$BLADE_ISSUES"
else
    log "Aucune référence hard-codée dans les vues Blade"
fi

# ================================================================
# ÉTAPE 7 — METTRE À JOUR BudgetPanelProvider
# ================================================================
header "ÉTAPE 7 — Rappel : BudgetPanelProvider"

info "Dans BudgetPanelProvider.php, vérifiez que discoverWidgets pointe vers :"
echo "    ->discoverWidgets("
echo "        in: app_path('Filament/Budget/Widgets'),"
echo "        for: 'App\\\\Filament\\\\Budget\\\\Widgets'"
echo "    )"
echo ""
info "Et que le WelcomeWidget est référencé avec son namespace complet :"
echo "    \\App\\Filament\\Widgets\\WelcomeWidget::class"

# ================================================================
# ÉTAPE 8 — VIDER LES CACHES
# ================================================================
header "ÉTAPE 8 — Vider les caches"

php artisan optimize:clear
log "Cache vidé"

if php artisan filament:cache-components 2>/dev/null; then
    log "Cache Filament vidé"
fi

# ================================================================
# ÉTAPE 9 — VÉRIFICATION FINALE
# ================================================================
header "ÉTAPE 9 — Vérification finale"

info "Vérification des namespaces résiduels..."

# Chercher d'éventuels namespaces anciens qui traîneraient dans Budget/
RESIDUAL=$(grep -r "namespace App\\\\Filament\\\\Resources;" \
    app/Filament/Budget/ 2>/dev/null || true)

if [ -n "$RESIDUAL" ]; then
    warn "Namespaces non corrigés dans Budget/ (à corriger manuellement) :"
    echo "$RESIDUAL"
else
    log "Tous les namespaces dans Budget/ sont corrects"
fi

# Compter les fichiers migrés
BUDGET_COUNT=$(find app/Filament/Budget/Resources -name "*.php" | wc -l)
WIDGET_COUNT=$(find app/Filament/Budget/Widgets -name "*.php" | wc -l)
ADMIN_COUNT=$(find app/Filament/Resources -name "*.php" | wc -l)

echo ""
echo -e "${GREEN}══════════════════════════════════════${NC}"
echo -e "${GREEN}  MIGRATION TERMINÉE${NC}"
echo -e "${GREEN}══════════════════════════════════════${NC}"
echo -e "  Budget/Resources : ${BUDGET_COUNT} fichiers PHP"
echo -e "  Budget/Widgets   : ${WIDGET_COUNT} fichiers PHP"
echo -e "  Resources admin  : ${ADMIN_COUNT} fichiers PHP (UserResource, RoleResource, etc.)"
echo ""
echo -e "${YELLOW}  PROCHAINES ÉTAPES :${NC}"
echo -e "  1. php artisan serve"
echo -e "  2. Ouvrir /budget dans le navigateur"
echo -e "  3. Vérifier chaque section de la sidebar"
echo -e "  4. En cas d'erreur : git diff pour voir les changements"
echo -e "${GREEN}══════════════════════════════════════${NC}"
