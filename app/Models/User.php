<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;  // ← Import Spatie

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;  // ← Ajouter HasRoles

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relations vers les bordereaux
     */
    public function bordereauxEmis()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'emis_par');
    }

    public function bordereauxReceptionnes()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'receptionne_par');
    }

    public function bordereauxValides()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'valide_par');
    }

    public function bordereauxRejetes()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'rejete_par');
    }

    public function bordereauxDetenus()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'detenu_par_id');
    }

    /**
     * Obtenir le nom du rôle principal
     */
    public function getRolePrincipalAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Obtenir le libellé du rôle principal
     */
    public function getRolePrincipalLabelAttribute(): ?string
    {
        $role = $this->roles->first()?->name;

        return match ($role) {
            'super_admin' => 'Super Admin',
            'operateur_budget' => 'Opérateur Budget',
            'chef_service_budget' => 'Chef Service Budget',
            'sous_directeur_budget' => 'Sous-Directeur Budget',
            'directeur_general' => 'Directeur Général',
            'controleur_financier' => 'Contrôleur Financier',
            'agence_comptable' => 'Agence Comptable',
            default => $role
        };
    }

    /**
     * Scope : Utilisateurs avec un rôle spécifique
     */
    public function scopeAvecRole($query, $role)
    {
        return $query->role($role);
    }

    /**
     * Scope : Utilisateurs validateurs (peuvent valider des bordereaux)
     */
    public function scopeValidateurs($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('name', [
                'chef_service_budget',
                'sous_directeur_budget',
                'directeur_general',
                'controleur_financier',
                'agence_comptable'
            ]);
        });
    }
}
