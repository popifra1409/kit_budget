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
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function decisionsAdministratives(): HasMany
    {
        return $this->hasMany(DecisionAdministrative::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===== HISTORIQUE FINANCIER (fiche agent) =====
    //
    // Les engagements et OP enregistrent le type de bénéficiaire sous deux formes
    // ('App\Models\Personnel' ou l'alias 'personnel') : les relations couvrent les deux.

    /** Engagements dont l'agent est bénéficiaire. */
    public function engagementsBeneficiaire(): HasMany
    {
        return $this->hasMany(Engagement::class, 'beneficiaire_id')
            ->whereIn('beneficiaire_type', Engagement::formesDuType(static::class));
    }

    /** Ordonnances de paiement émises au nom de l'agent. */
    public function ordonnancesBeneficiaire(): HasMany
    {
        return $this->hasMany(OrdonnancePaiement::class, 'beneficiaire_id')
            ->whereIn('beneficiaire_type', Engagement::formesDuType(static::class));
    }

    /** Avenants de changement de bénéficiaire dont l'agent est le NOUVEAU bénéficiaire. */
    public function avenantsBeneficiaire(): HasMany
    {
        return $this->hasMany(Avenant::class, 'beneficiaire_corrige_id')
            ->whereIn('beneficiaire_corrige_type', Engagement::formesDuType(static::class));
    }

    /**
     * Synthèse financière, tous exercices confondus.
     * - Engagé : montant des engagements actifs (brut) ;
     * - Ordonnancé / payé : OP standard non annulées (net versé à l'agent).
     */
    public function syntheseHistorique(): array
    {
        $engagements = $this->engagementsBeneficiaire()
            ->withoutGlobalScope('exercice')
            ->whereNull('date_annulation')
            ->where('statut', '!=', 'annule');

        $ordonnances = $this->ordonnancesBeneficiaire()
            ->withoutGlobalScope('exercice')
            ->where('type_ordonnance', 'standard')
            ->where('statut', '!=', 'annulee');

        $totalOrdonnance = (float) (clone $ordonnances)->sum('montant_net');
        $totalPaye = (float) (clone $ordonnances)->where('statut', 'payee')->sum('montant_net');

        return [
            'nb_decisions'     => $this->decisionsAdministratives()->withoutGlobalScope('exercice')->count(),
            'nb_engagements'   => (clone $engagements)->count(),
            'total_engage'     => (float) (clone $engagements)->sum('montant_engage'),
            'total_ordonnance' => $totalOrdonnance,
            'total_paye'       => $totalPaye,
            'reste_a_payer'    => $totalOrdonnance - $totalPaye,
        ];
    }

    /** L'agent a-t-il un historique (DA, engagement ou OP), tous exercices confondus ? */
    public function aUnHistorique(): bool
    {
        return $this->decisionsAdministratives()->withoutGlobalScope('exercice')->exists()
            || $this->engagementsBeneficiaire()->withoutGlobalScope('exercice')->exists()
            || $this->ordonnancesBeneficiaire()->withoutGlobalScope('exercice')->exists();
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
        return \DB::transaction(function () {
            $annee = now()->year;
            $prefixe = "MAT-{$annee}-";

            // ✅ LOCK pour éviter les conditions de course
            // Pendant cette transaction, aucun autre utilisateur ne peut lire ces lignes
            $personnels = static::where('matricule', 'like', "{$prefixe}%")
                ->lockForUpdate()
                ->get();

            // Trouver le numéro maximum
            $dernierNumero = $personnels
                ->map(function ($personnel) {
                    if (preg_match('/MAT-\d{4}-(\d+)$/', $personnel->matricule, $matches)) {
                        return (int) $matches[1];
                    }
                    return 0;
                })
                ->max() ?? 0;

            $sequence = $dernierNumero + 1;
            $matricule = sprintf('MAT-%s-%04d', $annee, $sequence);

            // Double vérification (normalement pas nécessaire avec le lock)
            if (static::where('matricule', $matricule)->exists()) {
                throw new \Exception("Matricule {$matricule} existe déjà (condition de course détectée)");
            }

            return $matricule;
        });
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
            // ✅ Générer automatiquement le matricule si vide
            if (empty($personnel->matricule)) {
                $personnel->matricule = static::genererMatricule();
            } else {
                // ✅ Si un matricule est fourni, vérifier l'unicité
                if (static::where('matricule', $personnel->matricule)->exists()) {
                    throw new \Exception("Le matricule {$personnel->matricule} existe déjà");
                }
            }

            $personnel->created_by = auth()->id();
        });

        static::updating(function ($personnel) {
            // ✅ Empêcher la modification du matricule (sauf par admin)
            if ($personnel->isDirty('matricule')) {
                $ancienMatricule = $personnel->getOriginal('matricule');
                $nouveauMatricule = $personnel->matricule;

                // Vérifier l'unicité du nouveau matricule
                if (static::where('matricule', $nouveauMatricule)
                    ->where('id', '!=', $personnel->id)
                    ->exists()
                ) {
                    throw new \Exception("Le matricule {$nouveauMatricule} existe déjà");
                }

                \Log::info("Matricule modifié", [
                    'personnel' => $personnel->id,
                    'ancien' => $ancienMatricule,
                    'nouveau' => $nouveauMatricule,
                    'par' => auth()->id(),
                ]);
            }

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
