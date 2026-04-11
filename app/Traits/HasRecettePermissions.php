<?php

namespace App\Traits\Filament;

/**
 * Trait HasRecettePermissions
 * 
 * Gère les permissions pour les ressources de recettes (réelles et prévisions)
 * avec contrôle par rôle et statut d'exercice
 */
trait HasRecettePermissions
{
    /**
     * Permissions - Consultation liste
     * 
     * Rôles autorisés à voir la liste des recettes
     */
    public static function canViewAny(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            'controleur_financier',
            'agence_comptable',
            'comptable' // Peut consulter pour suivi
        ]) : false;
    }

    /**
     * Permissions - Consultation détail
     * 
     * Rôles autorisés à voir le détail d'une recette
     */
    public static function canView($record): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'operateur_budget',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            'controleur_financier',
            'agence_comptable',
            'comptable'
        ]) : false;
    }

    /**
     * Permissions - Création
     * 
     * Rôles autorisés à créer une recette
     * - Chef service budget : peut créer des prévisions
     * - Opérateur budget : peut saisir les recettes sous supervision
     */
    public static function canCreate(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'operateur_budget',      // Peut saisir
            'chef_service_budget'     // Peut créer et valider
        ]) : false;
    }

    /**
     * Permissions - Édition
     * 
     * Règles :
     * - Super admin : peut toujours modifier
     * - Chef service budget : peut modifier si exercice modifiable
     * - Opérateur budget : peut modifier ses propres saisies si exercice modifiable
     * - Autres : lecture seule
     */
    public static function canEdit($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Super admin : toujours autorisé
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Vérifier que l'exercice permet la modification
        if (!$record->estModifiable()) {
            return false;
        }

        // Chef service budget : peut modifier
        if ($user->hasRole('chef_service_budget')) {
            return true;
        }

        // Opérateur budget : peut modifier uniquement ses propres saisies
        if ($user->hasRole('operateur_budget')) {
            return $record->created_by === $user->id;
        }

        return false;
    }

    /**
     * Permissions - Suppression
     * 
     * Règles strictes :
     * - Super admin : peut toujours supprimer
     * - Chef service budget : peut supprimer si exercice modifiable ET recette non validée
     * - Autres : non autorisé
     */
    public static function canDelete($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Super admin : toujours autorisé
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Chef service budget : peut supprimer si conditions remplies
        if ($user->hasRole('chef_service_budget')) {
            return $record->estModifiable() && !$record->estValidee();
        }

        return false;
    }

    /**
     * Permissions - Édition avec notification
     * 
     * Vérifie les permissions et affiche une notification si refusé
     */
    public static function canEditRecord($record): bool
    {
        $canEdit = static::canEdit($record);

        if (!$canEdit && $record->estLectureSeule()) {
            \Filament\Notifications\Notification::make()
                ->title('Édition impossible')
                ->warning()
                ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}. Seul un super admin peut modifier.")
                ->send();
        }

        return $canEdit;
    }

    /**
     * Action spéciale : Valider une recette
     * 
     * Rôles autorisés :
     * - Chef service budget : peut valider
     * - Contrôleur financier : peut valider
     * - Super admin : peut toujours valider
     */
    public static function canValider($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Ne pas valider une recette déjà validée
        if ($record->estValidee()) {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin',
            'chef_service_budget',
            'controleur_financier'
        ]);
    }

    /**
     * Action spéciale : Annuler une recette
     * 
     * Rôles autorisés :
     * - Chef service budget : peut annuler
     * - Directeur général : peut annuler
     * - Super admin : peut toujours annuler
     */
    public static function canAnnuler($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Ne pas annuler une recette déjà annulée
        if ($record->statut === 'annulee') {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin',
            'chef_service_budget',
            'directeur_general'
        ]);
    }

    /**
     * Action spéciale : Marquer comme réalisée (pour RecetteReelle)
     * 
     * Rôles autorisés :
     * - Agence comptable : peut marquer comme réalisée
     * - Comptable : peut marquer comme réalisée
     * - Chef service budget : peut marquer comme réalisée
     * - Super admin : peut toujours marquer
     */
    public static function canMarquerRealisee($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Ne pas marquer si déjà réalisée
        if ($record->statut === 'realisee' || $record->statut === 'encaissee') {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin',
            'agence_comptable',
            'comptable',
            'chef_service_budget'
        ]);
    }

    /**
     * Action spéciale : Marquer comme encaissée (pour RecetteReelle)
     * 
     * Rôles autorisés (très restreint) :
     * - Agence comptable : peut marquer comme encaissée
     * - Super admin : peut toujours marquer
     */
    public static function canMarquerEncaissee($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Ne peut encaisser que si réalisée
        if ($record->statut !== 'realisee') {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin',
            'agence_comptable'
        ]);
    }

    /**
     * Action spéciale : Approuver (pour PrevisionRecette)
     * 
     * Rôles autorisés :
     * - Directeur général : peut approuver
     * - Super admin : peut toujours approuver
     */
    public static function canApprouver($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Ne peut approuver que si validée
        if (!$record->estValidee() || $record->estApprouvee()) {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin',
            'directeur_general'
        ]);
    }

    /**
     * Action spéciale : Réviser une prévision (pour PrevisionRecette)
     * 
     * Rôles autorisés :
     * - Chef service budget : peut réviser
     * - Super admin : peut toujours réviser
     */
    public static function canReviser($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Peut réviser si approuvée et exercice en cours
        if (!$record->estApprouvee() || $record->exercice->statut !== 'en_cours') {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin',
            'chef_service_budget'
        ]);
    }

    /**
     * Action spéciale : Exporter (pour rapports)
     * 
     * Rôles autorisés : tous sauf opérateur
     */
    public static function canExporter(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget',
            'sous_directeur_budget',
            'directeur_general',
            'controleur_financier',
            'agence_comptable',
            'comptable'
        ]) : false;
    }

    /**
     * Vérifier si l'utilisateur peut effectuer des actions de masse
     */
    public static function canBulkAction(): bool
    {
        return auth()->check() ? auth()->user()->hasAnyRole([
            'super_admin',
            'chef_service_budget'
        ]) : false;
    }
}
