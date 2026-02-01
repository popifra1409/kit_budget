<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'niveau_hierarchique',
    ];

    protected $casts = [
        'niveau_hierarchique' => 'integer',
    ];

    /**
     * Obtenir les rôles supérieurs hiérarchiquement
     */
    public function rolesSuperieurs()
    {
        return static::where('niveau_hierarchique', '>', $this->niveau_hierarchique)
            ->orderBy('niveau_hierarchique', 'asc')
            ->get();
    }

    /**
     * Obtenir les rôles inférieurs hiérarchiquement
     */
    public function rolesInferieurs()
    {
        return static::where('niveau_hierarchique', '<', $this->niveau_hierarchique)
            ->orderBy('niveau_hierarchique', 'desc')
            ->get();
    }

    /**
     * Vérifier si ce rôle est supérieur à un autre
     */
    public function estSuperieurA(Role $autreRole): bool
    {
        return $this->niveau_hierarchique > $autreRole->niveau_hierarchique;
    }
}
