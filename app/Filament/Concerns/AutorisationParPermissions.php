<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Contrôle d'accès COMPLET d'une ressource Filament par les permissions Spatie
 * du seeder : view_any_X, view_X, create_X, update_X, delete_X.
 *
 * Sans policy, Filament autorise par défaut la création, la modification et
 * la suppression, y compris par saisie directe de l'adresse (/admin/users/1/edit).
 * Ce trait ferme ces accès : chaque action exige sa permission.
 *
 * Utilisation dans une ressource :
 *     use AutorisationParPermissions;
 *     protected static function prefixePermission(): string { return 'user'; }
 */
trait AutorisationParPermissions
{
    abstract protected static function prefixePermission(): string;

    protected static function autorise(string $action): bool
    {
        return auth()->user()?->can($action . '_' . static::prefixePermission()) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::autorise('view_any');
    }

    public static function canView(Model $record): bool
    {
        return static::autorise('view') || static::autorise('view_any');
    }

    public static function canCreate(): bool
    {
        return static::autorise('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::autorise('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::autorise('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::autorise('delete');
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::autorise('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::autorise('delete');
    }

    public static function canRestore(Model $record): bool
    {
        return static::autorise('update');
    }

    public static function canRestoreAny(): bool
    {
        return static::autorise('update');
    }
}
