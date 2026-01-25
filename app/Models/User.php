<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'actif',
        'service_id',
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
            'actif' => 'boolean',
        ];
    }

    /**
     * Déterminer si l'utilisateur peut accéder au panel Filament
     * 
     * @param Panel $panel
     * @return bool
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Vérifier d'abord si l'utilisateur est actif
        if (!$this->actif) {
            session(['compte_inactif' => true]);
            return false;
        }

        // Vérifier que l'utilisateur a un rôle approprié
        return $this->hasAnyRole([
            'super_admin',
            'admin',
            'directeur_general',
            'daaf',
            'sous_directeur_budget',
            'chef_service_budget',
            'operateur_budget',
            'controleur_financier',
            'agence_comptable',
        ]);
    }

    /**
     * Observer pour garantir que super_admin reste toujours actif
     */
    protected static function boot()
    {
        parent::boot();

        // Avant de sauvegarder
        static::saving(function ($user) {
            // PROTECTION ABSOLUE : Si l'utilisateur est super_admin, forcer actif = true
            if ($user->hasRole('super_admin')) {
                $user->actif = true;
            }
        });

        // Avant de mettre à jour
        static::updating(function ($user) {
            // PROTECTION ABSOLUE : Si l'utilisateur est super_admin, forcer actif = true
            if ($user->hasRole('super_admin')) {
                $user->actif = true;
            }
        });
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope pour filtrer les utilisateurs actifs
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour filtrer les utilisateurs inactifs
     */
    public function scopeInactif($query)
    {
        return $query->where('actif', false);
    }

    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================

    /**
     * Vérifier si l'utilisateur est actif
     */
    public function isActif(): bool
    {
        return $this->actif === true;
    }

    /**
     * Activer l'utilisateur
     */
    public function activer(): void
    {
        $this->update(['actif' => true]);
    }

    /**
     * Désactiver l'utilisateur (sauf si super_admin)
     */
    public function desactiver(): void
    {
        // PROTECTION : Ne jamais désactiver un super_admin
        if ($this->hasRole('super_admin')) {
            throw new \Exception('Un compte Super Admin ne peut pas être désactivé.');
        }

        $this->update(['actif' => false]);
    }

    // ========================================
    // RELATIONS BORDEREAUX ENGAGEMENT
    // COLONNES RÉELLES : emis_par, detenu_par_id, valide_par, rejete_par
    // ========================================

    /**
     * Bordereaux créés/émis par cet utilisateur
     * Utilisé dans ViewUser (bordereauxEmis_count)
     * Colonne : emis_par
     */
    public function bordereauxEmis()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'emis_par');
    }

    /**
     * Bordereaux validés par cet utilisateur
     * Utilisé dans ViewUser (bordereauxValides_count)
     * Colonne : valide_par
     */
    public function bordereauxValides()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'valide_par')
            ->where('statut', 'valide');
    }

    /**
     * Bordereaux rejetés par cet utilisateur
     * Utilisé dans ViewUser (bordereauxRejetes_count)
     * Colonne : rejete_par
     */
    public function bordereauxRejetes()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'rejete_par')
            ->whereIn('statut', ['rejete_total', 'rejete_partiel']);
    }

    /**
     * Bordereaux en cours (détenus) par cet utilisateur pour validation
     * Utilisé dans ViewUser (bordereauxDetenus_count)
     * Colonne : detenu_par_id
     */
    public function bordereauxDetenus()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'detenu_par_id')
            ->whereIn('statut', ['transmis', 'en_cours']);
    }

    /**
     * Bordereaux réceptionnés par cet utilisateur
     * Colonne : receptionne_par
     */
    public function bordereauxReceptionnes()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'receptionne_par');
    }

    /**
     * Alias pour bordereauxDetenus (bordereaux en attente de validation)
     */
    public function bordereauxEnAttente()
    {
        return $this->bordereauxDetenus();
    }

    /**
     * Tous les bordereaux dont cet utilisateur est le détenteur actuel
     */
    public function bordereauxAValider()
    {
        return $this->hasMany(\App\Models\BordereauEngagement::class, 'detenu_par_id');
    }

    // ========================================
    // AUTRES RELATIONS
    // ========================================

    /**
     * Service auquel appartient l'utilisateur
     */
    public function service()
    {
        return $this->belongsTo(\App\Models\Service::class);
    }

    /**
     * Engagements créés par l'utilisateur
     */
    public function engagements()
    {
        return $this->hasMany(\App\Models\Engagement::class, 'createur_id');
    }

    /**
     * Recettes réelles créées par l'utilisateur
     */
    public function recettesReelles()
    {
        return $this->hasMany(\App\Models\RecetteReelle::class, 'createur_id');
    }

    /**
     * Dépenses réelles créées par l'utilisateur
     */
    public function depensesReelles()
    {
        return $this->hasMany(\App\Models\DepenseReelle::class, 'createur_id');
    }

    // ========================================
    // MÉTHODES HELPER POUR BORDEREAUX
    // ========================================

    /**
     * Nombre de bordereaux en attente de validation
     */
    public function getNombreBordereausEnAttenteAttribute(): int
    {
        return $this->bordereauxDetenus()->count();
    }

    /**
     * Nombre de bordereaux validés
     */
    public function getNombreBordereausValidesAttribute(): int
    {
        return $this->bordereauxValides()->count();
    }

    /**
     * Nombre de bordereaux rejetés
     */
    public function getNombreBordereausRejetesAttribute(): int
    {
        return $this->bordereauxRejetes()->count();
    }

    /**
     * Nombre de bordereaux émis
     */
    public function getNombreBordereausEmisAttribute(): int
    {
        return $this->bordereauxEmis()->count();
    }

    /**
     * Peut valider un bordereau ?
     */
    public function peutValiderBordereau(\App\Models\BordereauEngagement $bordereau): bool
    {
        // Le détenteur actuel doit être cet utilisateur
        if ($bordereau->detenu_par_id !== $this->id) {
            return false;
        }

        // Le bordereau doit être transmis ou en cours
        if (!in_array($bordereau->statut, ['transmis', 'en_cours'])) {
            return false;
        }

        // Vérifier les permissions selon le rôle
        return $this->hasAnyRole([
            'super_admin',
            'admin',
            'chef_service_budget',
            'daaf',
            'directeur_general',
            'controleur_financier',
            'agence_comptable',
        ]);
    }

    /**
     * Peut créer un bordereau ?
     */
    public function peutCreerBordereau(): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'admin',
            'operateur_budget',
            'chef_service_budget',
        ]);
    }

    /**
     * Peut voir tous les bordereaux ?
     */
    public function peutVoirTousBordereaux(): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'admin',
            'directeur_general',
            'daaf',
            'sous_directeur_budget',
            'controleur_financier',
            'agence_comptable',
        ]);
    }
}
