<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Traits\HasWorkflow;

class Engagement extends Model
{
    use HasFactory, SoftDeletes, HasExercice, HasWorkflow, LogsActivity;

    protected $fillable = [
        'exercice_id',
        'numero',
        'budget_id',
        'type_engagement',
        'nomenclature_principale_id',
        'reference_document',
        'engageable_type',
        'engageable_id',
        'beneficiaire_type',
        'beneficiaire_id',
        'date_engagement',
        'exercice',
        'objet',
        'montant_engage',
        'statut',
        'engage_par',
        'date_validation',
        'observations',
    ];

    protected $casts = [
        'date_engagement' => 'date',
        'date_validation' => 'datetime',
        'montant_engage' => 'decimal:2',
        'exercice' => 'integer',
    ];

    /**
     * Boot - Générer le numéro automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($engagement) {
            if (empty($engagement->numero)) {
                $engagement->numero = $engagement->genererNumero();
            }

            if (empty($engagement->exercice)) {
                $engagement->exercice = now()->year;
            }
        });
    }

    /**
     * Relation : Budget
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Relation : Nomenclature budgétaire principale
     */
    public function nomenclaturePrincipale(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_principale_id');
    }

    /**
     * Relation : Objet engagé (polymorphique)
     * BonCommande, DecisionAdministrative, Marche, etc.
     */
    public function engageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * ✅ Méthode helper pour obtenir le BC si c'est le type engageable
     */
    public function getBonCommandeAttribute()
    {
        // Charger la relation si nécessaire
        if (!$this->relationLoaded('engageable')) {
            $this->load('engageable');
        }

        // Vérifier le type
        if ($this->engageable instanceof \App\Models\BonCommande) {
            return $this->engageable;
        }

        return null;
    }

    /**
     * ✅ MÉTHODE ALTERNATIVE : Obtenir le bon de commande
     * Plus explicite et peut être appelée comme méthode
     */
    public function obtenirBonCommande(): ?\App\Models\BonCommande
    {
        $this->loadMissing('engageable');

        return $this->engageable instanceof \App\Models\BonCommande
            ? $this->engageable
            : null;
    }

    /**
     * ✅ Vérifier si l'engagement est lié à un BC
     */
    public function estBonCommande(): bool
    {
        return $this->engageable_type === \App\Models\BonCommande::class
            || $this->engageable_type === 'App\Models\BonCommande';
    }

    /**
     * ✅ Vérifier si l'engagement est lié à une Décision
     */
    public function estDecision(): bool
    {
        return $this->engageable_type === \App\Models\DecisionAdministrative::class
            || $this->engageable_type === 'App\Models\DecisionAdministrative';
    }

    /**
     * Relation : Bénéficiaire (polymorphique)
     * Fournisseur, User, etc.
     */
    public function beneficiaire(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relation : Lignes d'engagement
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneEngagement::class);
    }

    /**
     * Relation : Engagé par
     */
    public function engagePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engage_par');
    }

    /**
     * Relation : Bordereaux (Many-to-Many)
     */
    public function bordereaux(): BelongsToMany
    {
        return $this->belongsToMany(
            BordereauEngagement::class,
            'bordereau_engagement_lignes',
            'engagement_id',
            'bordereau_id'
        )->withPivot('numero_ligne', 'statut_ligne', 'motif_rejet', 'observations')
            ->withTimestamps();
    }

    /**
     * Alias pour bordereaux() - Compatibilité avec bordereauEngagements()
     */
    public function bordereauEngagements(): BelongsToMany
    {
        return $this->bordereaux();
    }

    /**
     * Scope : Par exercice
     */
    public function scopeExercice($query, $exercice)
    {
        return $query->where('exercice', $exercice);
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : Par type d'engageable
     */
    public function scopeType($query, $type)
    {
        return $query->where('engageable_type', $type);
    }

    /**
     * Générer le numéro d'engagement
     * Format: BE-YYYY-XXXXX
     */
    public function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = self::where('numero', 'like', "BE-{$annee}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier) {
            $dernierNumero = intval(substr($dernier->numero, -5));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }

        return sprintf('BE-%d-%05d', $annee, $nouveauNumero);
    }

    /**
     * Passer en statut définitif
     */
    public function passerDefinitif(User $user): void
    {
        $this->statut = 'definitif';
        $this->engage_par = $user->id;
        $this->date_validation = now();
        $this->save();
    }

    /**
     * Annuler l'engagement
     */
    public function annuler(): void
    {
        if ($this->statut === 'solde') {
            throw new \Exception("Impossible d'annuler un engagement soldé");
        }

        DB::transaction(function () {
            foreach ($this->lignes as $ligne) {
                $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $ligne->nomenclature_id)
                    ->firstOrFail();

                $ligneBudgetaire->annulerEngagement($ligne->montant);
            }

            $this->update(['statut' => 'annule']);
        });
    }

    /**
     * Solder l'engagement
     */
    public function solder(): void
    {
        if ($this->statut !== 'definitif') {
            throw new \Exception("Seul un engagement définitif peut être soldé");
        }

        $this->statut = 'solde';
        $this->save();
    }

    /**
     * Obtenir le type d'engagement en français
     */
    public function getTypeLabel(): string
    {
        return match ($this->engageable_type) {
            'App\Models\BonCommande' => 'Bon de Commande',
            'App\Models\DecisionAdministrative' => 'Décision Administrative',
            'App\Models\Marche' => 'Marché',
            default => class_basename($this->engageable_type),
        };
    }

    /**
     * Obtenir le nom du bénéficiaire
     */
    public function getNomBeneficiaire(): string
    {
        if (!$this->beneficiaire) {
            return 'N/A';
        }

        return match ($this->beneficiaire_type) {
            'App\Models\Fournisseur' => $this->beneficiaire->raison_sociale,
            'App\Models\User' => $this->beneficiaire->name,
            default => 'Inconnu',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'numero',
                'budget_id',
                'exercice_id',
                'statut',
                'montant_engage',
                'date_validation'
            ])
            ->logOnlyDirty();
    }
    /**
     * Relation : Bon de commande (si créé via BC)
     */
    // public function bonCommande(): BelongsTo
    // {
    //     return $this->belongsTo(BonCommande::class, 'bon_commande_id');
    // }

    // public function bonCommande()
    // {
    //     return $this->hasOne(BonCommande::class, 'bon_commande_id');
    // }


    /**
     * Créer les ordonnances de paiement (Standard + Impôt)
     */
    public function creerOrdonnancesPaiement(): array
    {
        $ordonnances = [];

        // Récupérer le BC lié via la relation
        $bonCommande = $this->bonCommande;

        // Déterminer le fournisseur/bénéficiaire
        $beneficiaire = null;
        $montantIR = 0;

        if ($bonCommande) {
            // ✅ Si on a un BC, utiliser creerDepuisBonCommande qui est plus complet
            return [
                'standard' => \App\Models\OrdonnancePaiement::creerDepuisBonCommande($bonCommande, 'standard'),
                'impot' => \App\Models\OrdonnancePaiement::creerDepuisBonCommande($bonCommande, 'impot'),
            ];
        }

        // ❌ Engagement sans BC - utiliser l'ancienne logique
        $beneficiaire = $this->beneficiaire ?? $this->engageable;

        if (!$beneficiaire) {
            throw new \Exception("Aucun bénéficiaire trouvé pour cet engagement. Veuillez définir un bénéficiaire.");
        }

        $montantBrut = $this->montant_engage;
        $montantNet = $montantBrut - $montantIR;

        // Log pour debug
        \Log::info('Création OP sans BC', [
            'engagement_id' => $this->id,
            'beneficiaire_id' => $beneficiaire->id,
            'beneficiaire_class' => get_class($beneficiaire),
            'montant_ir' => $montantIR,
        ]);

        // 1. OP Standard (pour le fournisseur/bénéficiaire)
        $opStandard = \App\Models\OrdonnancePaiement::create([
            'numero' => \App\Models\OrdonnancePaiement::genererNumeroFromEngagement($this, 'standard'),
            'exercice_id' => $this->exercice_id,
            'type_ordonnance' => 'standard',
            'engagement_id' => $this->id,
            'beneficiaire_type' => get_class($beneficiaire),
            'beneficiaire_id' => $beneficiaire->id,
            'objet' => $this->objet,
            'montant_brut' => $montantBrut,
            'montant_impot' => $montantIR,
            'montant_net' => $montantNet,
            'date_emission' => now(),
            'mois_emission' => now()->format('m'),
            'numero_emission' => $this->numero ?? null,
            'numero_op' => \App\Models\OrdonnancePaiement::genererNumeroFromEngagement($this, 'standard'),
            'periode' => now()->format('m/Y'),
            'statut' => 'brouillon',
            'created_by' => auth()->id(),
        ]);

        $ordonnances['standard'] = $opStandard;

        // 2. OP Impôt (si IR > 0)
        if ($montantIR > 0) {
            $opImpot = \App\Models\OrdonnancePaiement::create([
                'numero' => \App\Models\OrdonnancePaiement::genererNumeroFromEngagement($this, 'impot'),
                'exercice_id' => $this->exercice_id,
                'type_ordonnance' => 'impot',
                'engagement_id' => $this->id,
                'beneficiaire_type' => null,
                'beneficiaire_id' => null,
                'objet' => "Reversement AIR",
                'montant_brut' => $montantIR,
                'montant_impot' => 0,
                'montant_net' => $montantIR,
                'montant_pec' => $montantNet,
                'date_emission' => now(),
                'mois_emission' => now()->format('m'),
                'numero_emission' => $this->numero ?? null,
                'numero_op' => \App\Models\OrdonnancePaiement::genererNumeroFromEngagement($this, 'impot'),
                'periode' => now()->format('m/Y'),
                'statut' => 'brouillon',
                'created_by' => auth()->id(),
            ]);

            $ordonnances['impot'] = $opImpot;
        }

        return $ordonnances;
    }

    /**
     * Vérifier si l'engagement a déjà des OP
     */
    public function hasOrdonnancesPaiement(): bool
    {
        return \App\Models\OrdonnancePaiement::where('engagement_id', $this->id)->exists();
    }

    /**
     * Obtenir les ordonnances de paiement liées
     */
    public function ordonnancesPaiement()
    {
        return $this->hasMany(\App\Models\OrdonnancePaiement::class);
    }
}
