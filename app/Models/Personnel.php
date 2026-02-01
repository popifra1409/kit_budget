<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Personnel extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'personnels';

    protected $fillable = [
        'matricule',
        'nom',
        'prenoms',
        'civilite',
        'date_naissance',
        'lieu_naissance',
        'sexe',
        'nationalite',
        'telephone',
        'email',
        'adresse',
        'service_id',
        'fonction',
        'grade',
        'categorie',
        'echelon',
        'indice',
        'date_prise_service',
        'date_titularisation',
        'date_depart_retraite',
        'numero_cni',
        'numero_cnps',
        'numero_compte_bancaire',
        'banque',
        'statut',
        'actif',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_prise_service' => 'date',
        'date_titularisation' => 'date',
        'date_depart_retraite' => 'date',
        'actif' => 'boolean',
    ];

    protected $appends = ['nom_complet'];

    // ===== RELATIONS =====

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decisionsAdministratives(): HasMany
    {
        return $this->hasMany(DecisionAdministrative::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===== ACCESSEURS =====

    public function getNomCompletAttribute(): string
    {
        return "{$this->nom} {$this->prenoms}";
    }

    public function getNomCompletAvecCiviliteAttribute(): string
    {
        $civilite = $this->civilite ? "{$this->civilite} " : "";
        return "{$civilite}{$this->nom} {$this->prenoms}";
    }

    public function getAgeAttribute(): ?int
    {
        if (!$this->date_naissance) {
            return null;
        }

        return $this->date_naissance->age;
    }

    public function getAncienneteAttribute(): ?int
    {
        if (!$this->date_prise_service) {
            return null;
        }

        return $this->date_prise_service->diffInYears(now());
    }

    // ===== SCOPES =====

    public function scopeActifs($query)
    {
        return $query->where('actif', true)
            ->where('statut', 'actif');
    }

    public function scopeParService($query, $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeRecherche($query, $terme)
    {
        return $query->where(function ($q) use ($terme) {
            $q->where('nom', 'ILIKE', "%{$terme}%")
                ->orWhere('prenoms', 'ILIKE', "%{$terme}%")
                ->orWhere('matricule', 'ILIKE', "%{$terme}%");
        });
    }

    // ===== MÉTHODES =====

    /**
     * Générer un matricule automatique
     */
    public static function genererMatricule(): string
    {
        $annee = now()->year;

        $dernier = static::where('matricule', 'like', "MAT-{$annee}-%")
            ->orderBy('matricule', 'desc')
            ->first();

        if ($dernier && preg_match('/MAT-\d{4}-(\d+)/', $dernier->matricule, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('MAT-%s-%04d', $annee, $sequence);
    }

    /**
     * Vérifier si proche de la retraite
     */
    public function estProcheRetraite(int $mois = 12): bool
    {
        if (!$this->date_depart_retraite) {
            return false;
        }

        return $this->date_depart_retraite->diffInMonths(now()) <= $mois;
    }

    // ===== OBSERVERS =====

    protected static function booted(): void
    {
        static::creating(function ($personnel) {
            // NE PLUS générer automatiquement le matricule
            // L'utilisateur doit le saisir manuellement

            $personnel->created_by = auth()->id();
        });

        static::updating(function ($personnel) {
            $personnel->updated_by = auth()->id();
        });
    }

    // ===== ACTIVITY LOG =====

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricule', 'nom', 'prenoms', 'fonction', 'statut'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
