<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasCorbeille;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, SoftDeletes, HasCorbeille;

    protected $fillable = [
        'code',
        'nom',
        'description',
        'parent_id',
        'responsable',
        'email',
        'telephone',
        'batiment',
        'bureau',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /**
     * Relation : Service parent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'parent_id');
    }

    /**
     * Relation : Services enfants (sous-services)
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Service::class, 'parent_id');
    }

    /**
     * Relation : Tâches dont ce service est responsable
     */
    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class, 'service_id');
    }

    /**
     * Relation : Bons de commande demandés par ce service
     */
    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class, 'service_demandeur_id');
    }

    /**
     * Scope : Services actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : Services racines (sans parent)
     */
    public function scopeRacines($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Obtenir le chemin hiérarchique complet
     */
    public function getCheminComplet(): string
    {
        $chemin = [$this->nom];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($chemin, $parent->nom);
            $parent = $parent->parent;
        }

        return implode(' > ', $chemin);
    }

    /**
     * Obtenir tous les services descendants
     */
    public function getTousEnfants()
    {
        $enfants = collect();

        foreach ($this->enfants as $enfant) {
            $enfants->push($enfant);
            $enfants = $enfants->merge($enfant->getTousEnfants());
        }

        return $enfants;
    }

    /**
     * Générer le code du service : « SRV-052 », à la suite du plus grand numéro déjà posé.
     *
     * Seuls les codes au format « SRV-NNN » sont comptés : le parc garde des legs
     * (« SVR-36 », « SRV-002-01 ») qui ne doivent pas faire redémarrer la séquence à 1.
     * La corbeille est lue elle aussi — l'index unique, lui, voit les lignes effacées.
     */
    public static function genererCode(): string
    {
        return \DB::transaction(function (): string {
            $dernierNumero = static::withTrashed()
                ->where('code', 'like', 'SRV-___')
                ->lockForUpdate()
                ->pluck('code')
                ->map(fn ($code) => (int) substr((string) $code, 4))
                ->max() ?? 0;

            $code = sprintf('SRV-%03d', $dernierNumero + 1);

            if (static::withTrashed()->where('code', $code)->exists()) {
                throw new \Exception("Le code {$code} est déjà utilisé (condition de course détectée)");
            }

            return $code;
        });
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            if (empty($service->code)) {
                $service->code = static::genererCode();
            }
        });
    }
}
