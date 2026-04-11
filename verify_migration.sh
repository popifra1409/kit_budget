#!/bin/bash
# ================================================================
# verify_migration.sh — À exécuter APRÈS migrate_to_budget.sh
# Vérifie que tout est en ordre avant de lancer le serveur
# ================================================================

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; BLUE='\033[0;34m'; NC='\033[0m'
ok()   { echo -e "${GREEN}  ✅ $1${NC}"; }
warn() { echo -e "${YELLOW}  ⚠️  $1${NC}"; }
fail() { echo -e "${RED}  ❌ $1${NC}"; ERRORS=$((ERRORS+1)); }
ERRORS=0

echo -e "${BLUE}══════════════════════════════════════════${NC}"
echo -e "${BLUE}  VÉRIFICATION POST-MIGRATION${NC}"
echo -e "${BLUE}══════════════════════════════════════════${NC}"

# ── 1. Vérifier que les dossiers existent
echo -e "\n${BLUE}[1] Structure des dossiers${NC}"
for dir in "app/Filament/Budget/Resources" "app/Filament/Budget/Widgets" "app/Filament/Budget/Pages"; do
    [ -d "$dir" ] && ok "$dir existe" || fail "$dir MANQUANT"
done

# ── 2. Vérifier que les resources clés sont bien déplacées
echo -e "\n${BLUE}[2] Resources Budget déplacées${NC}"
EXPECTED_BUDGET=(
    "BonCommandeResource.php"
    "EngagementResource.php"
    "MemoireDepenseResource.php"
    "BudgetResource.php"
    "ExerciceResource.php"
    "FournisseurResource.php"
    "OrdonnancePaiementResource.php"
    "VirementBudgetaireResource.php"
)
for f in "${EXPECTED_BUDGET[@]}"; do
    [ -f "app/Filament/Budget/Resources/$f" ] && \
        ok "$f → Budget/Resources/" || \
        fail "$f introuvable dans Budget/Resources/"
done

# ── 3. Vérifier que les resources admin sont toujours en place
echo -e "\n${BLUE}[3] Resources Admin conservées${NC}"
EXPECTED_ADMIN=("UserResource.php" "RoleResource.php" "PermissionResource.php" "PersonnelResource.php" "ServiceResource.php")
for f in "${EXPECTED_ADMIN[@]}"; do
    [ -f "app/Filament/Resources/$f" ] && \
        ok "$f → Resources/ (admin)" || \
        fail "$f manquant dans Resources/"
done

# ── 4. Vérifier les namespaces dans Budget/
echo -e "\n${BLUE}[4] Namespaces dans Budget/${NC}"

# Chercher les vieux namespaces qui traîneraient encore
OLD_NS=$(grep -r "namespace App\\\\Filament\\\\Resources;" app/Filament/Budget/ 2>/dev/null | head -5)
[ -z "$OLD_NS" ] && ok "Aucun ancien namespace trouvé" || fail "Ancien namespace détecté :\n$OLD_NS"

# Vérifier que les nouveaux namespaces sont bien là
NEW_NS_COUNT=$(grep -r "namespace App\\\\Filament\\\\Budget" app/Filament/Budget/ 2>/dev/null | wc -l)
[ "$NEW_NS_COUNT" -gt 0 ] && ok "$NEW_NS_COUNT fichiers avec namespace Budget" || fail "Aucun namespace Budget trouvé"

# ── 5. Vérifier les use statements dans les Resources Budget
echo -e "\n${BLUE}[5] Use statements dans Budget/Resources${NC}"

# Les resources Budget ne doivent plus référencer App\Filament\Resources\Xxx
# (sauf pour les resources Admin qui restent dans l'ancien namespace)
OLD_USE=$(grep -r "use App\\\\Filament\\\\Resources\\\\Bon\|use App\\\\Filament\\\\Resources\\\\Engagement\|use App\\\\Filament\\\\Resources\\\\Budget" \
    app/Filament/Budget/ 2>/dev/null | head -5)
[ -z "$OLD_USE" ] && ok "Use statements Budget corrects" || \
    warn "Use statements à vérifier :\n$OLD_USE"

# ── 6. Vérifier les fichiers de pages internes
echo -e "\n${BLUE}[6] Pages internes des Resources${NC}"

PAGES_COUNT=$(find app/Filament/Budget/Resources -mindepth 2 -name "*.php" | wc -l)
ok "$PAGES_COUNT fichiers de pages/relationManagers dans Budget/Resources"

# Vérifier un namespace de page interne au hasard
SAMPLE_PAGE=$(find app/Filament/Budget/Resources -mindepth 2 -name "*.php" | head -1)
if [ -n "$SAMPLE_PAGE" ]; then
    NS_CHECK=$(grep "^namespace" "$SAMPLE_PAGE")
    if echo "$NS_CHECK" | grep -q "Budget"; then
        ok "Exemple OK : $SAMPLE_PAGE → $NS_CHECK"
    else
        fail "Namespace incorrect dans $SAMPLE_PAGE : $NS_CHECK"
    fi
fi

# ── 7. Vérifier le BudgetPanelProvider
echo -e "\n${BLUE}[7] BudgetPanelProvider${NC}"
PROVIDER="app/Providers/Filament/BudgetPanelProvider.php"
if [ -f "$PROVIDER" ]; then
    grep -q "Filament/Budget/Resources" "$PROVIDER" && \
        ok "discoverResources pointe vers Budget/" || \
        fail "discoverResources NE POINTE PAS vers Budget/ dans BudgetPanelProvider"
    grep -q "Filament/Budget/Widgets" "$PROVIDER" && \
        ok "discoverWidgets pointe vers Budget/" || \
        fail "discoverWidgets NE POINTE PAS vers Budget/"
else
    warn "BudgetPanelProvider.php non trouvé (à créer)"
fi

# ── 8. Vérifier bootstrap/providers.php
echo -e "\n${BLUE}[8] Enregistrement dans bootstrap/providers.php${NC}"
PROVIDERS_FILE="bootstrap/providers.php"
if [ -f "$PROVIDERS_FILE" ]; then
    grep -q "BudgetPanelProvider" "$PROVIDERS_FILE" && \
        ok "BudgetPanelProvider enregistré" || \
        warn "BudgetPanelProvider NON enregistré dans bootstrap/providers.php"
    grep -q "AdminPanelProvider" "$PROVIDERS_FILE" && \
        warn "AdminPanelProvider encore présent — à supprimer ou garder ?" || \
        ok "AdminPanelProvider supprimé"
else
    warn "bootstrap/providers.php introuvable"
fi

# ── 9. Test artisan (détecte les erreurs PHP)
echo -e "\n${BLUE}[9] Test de chargement PHP${NC}"
if php artisan about --only=environment 2>/dev/null | grep -q "PHP"; then
    ok "php artisan fonctionne (pas d'erreur PHP fatale)"
else
    fail "Erreur PHP détectée — vérifiez php artisan about"
fi

# ── Résumé
echo ""
echo -e "${BLUE}══════════════════════════════════════════${NC}"
if [ "$ERRORS" -eq 0 ]; then
    echo -e "${GREEN}  ✅ MIGRATION VALIDÉE — $ERRORS erreur(s)${NC}"
    echo -e "${GREEN}  Lancez : php artisan serve${NC}"
    echo -e "${GREEN}  Puis ouvrez : http://localhost:8000/budget${NC}"
else
    echo -e "${RED}  ❌ $ERRORS ERREUR(S) DÉTECTÉE(S) — à corriger${NC}"
fi
echo -e "${BLUE}══════════════════════════════════════════${NC}"
