<?php

namespace App\Traits;

/**
 * Trait HasRecentValues
 * 
 * Permet de récupérer facilement les dernières valeurs distinctes
 * d'une colonne pour l'autocomplétion.
 * 
 * UTILISATION :
 * 
 * 1. Ajouter le trait au modèle :
 *    use HasRecentValues;
 * 
 * 2. Dans le formulaire Filament :
 *    ->options(fn() => MonModele::getRecentValues('colonne', 20))
 *    ->getSearchResultsUsing(fn($search) => MonModele::searchRecentValues('colonne', $search))
 */
trait HasRecentValues
{
    /**
     * Obtenir les dernières valeurs distinctes d'une colonne
     * 
     * @param string $column Nom de la colonne
     * @param int $limit Nombre maximum de valeurs (défaut: 20)
     * @param string|null $orderBy Colonne pour trier (défaut: created_at)
     * @return array
     * 
     * Exemple :
     * BonCommande::getRecentValues('objet', 30)
     */
    public static function getRecentValues(
        string $column,
        int $limit = 20,
        ?string $orderBy = 'created_at'
    ): array {
        return static::query()
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($orderBy ?? 'created_at', 'desc')
            ->limit($limit)
            ->pluck($column, $column)
            ->toArray();
    }

    /**
     * Rechercher dans les valeurs récentes
     * 
     * @param string $column Nom de la colonne
     * @param string $search Terme de recherche
     * @param int $limit Nombre maximum de résultats (défaut: 10)
     * @return array
     * 
     * Exemple :
     * BonCommande::searchRecentValues('objet', 'fourniture')
     */
    public static function searchRecentValues(
        string $column,
        string $search,
        int $limit = 10
    ): array {
        return static::query()
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->where($column, 'ilike', "%{$search}%")  // ilike pour PostgreSQL (insensible à la casse)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->pluck($column, $column)
            ->toArray();
    }

    /**
     * Obtenir les valeurs récentes avec compteur d'utilisation
     * 
     * @param string $column Nom de la colonne
     * @param int $limit Nombre maximum de valeurs
     * @return array Format: ['valeur' => 'valeur (utilisé X fois)']
     * 
     * Exemple :
     * BonCommande::getRecentValuesWithCount('objet', 15)
     * // Retourne: ['Fournitures' => 'Fournitures (utilisé 12 fois)']
     */
    public static function getRecentValuesWithCount(
        string $column,
        int $limit = 20
    ): array {
        $results = static::query()
            ->select($column, \DB::raw('COUNT(*) as count'))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->orderBy('count', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $results->mapWithKeys(function ($item) use ($column) {
            $value = $item->{$column};
            $label = "{$value} (utilisé {$item->count} fois)";
            return [$value => $label];
        })->toArray();
    }

    /**
     * Obtenir la dernière valeur utilisée pour une colonne donnée
     * 
     * @param string $column Nom de la colonne
     * @param mixed $where Conditions supplémentaires (optionnel)
     * @return mixed|null
     * 
     * Exemple :
     * BonCommande::getLastValue('lieu_livraison')
     * BonCommande::getLastValue('objet', ['budget_id' => 5])
     */
    public static function getLastValue(string $column, array $where = [])
    {
        $query = static::query()
            ->whereNotNull($column)
            ->where($column, '!=', '');

        foreach ($where as $key => $value) {
            $query->where($key, $value);
        }

        return $query->orderBy('created_at', 'desc')
            ->value($column);
    }
}
