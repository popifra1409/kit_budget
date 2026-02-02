<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasExercice;

class OrdonnancePaiement extends Model
{
    use HasFactory, SoftDeletes, HasExercice;

    protected $table = 'ordonnances_paiement';

    protected $fillable = [
        'numero',
        'exercice_id',
        'type_ordonnance',
        'engagement_id',
        'beneficiaire_type',
        'beneficiaire_id',
        'objet',
        'montant_brut',
        'montant_impot',
        'montant_net',
        'montant_pec',
        // ✅ AJOUT : Détail des impôts
        'montant_tva',
        'montant_ir',
        'montant_tsr',
        'montant_cnps',
        'montant_irnc',
        'montant_autres_taxes',
        // Fin ajout
        'date_emission',
        'mois_emission',
        'numero_bon',
        'numero_emission',
        'numero_op',
        'periode',
        'statut',
        'date_paiement',
        'reference_paiement',
        'observations',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'date_paiement' => 'date',
        'montant_brut' => 'decimal:2',
        'montant_impot' => 'decimal:2',
        'montant_net' => 'decimal:2',
        'montant_pec' => 'decimal:2',
        // ✅ AJOUT : Casts pour les impôts
        'montant_tva' => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'montant_tsr' => 'decimal:2',
        'montant_cnps' => 'decimal:2',
        'montant_irnc' => 'decimal:2',
        'montant_autres_taxes' => 'decimal:2',
        // Fin ajout
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function beneficiaire(): MorphTo
    {
        return $this->morphTo();
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeBrouillon($query)
    {
        return $query->where('statut', 'brouillon');
    }

    public function scopeEmise($query)
    {
        return $query->where('statut', 'emise');
    }

    public function scopePayee($query)
    {
        return $query->where('statut', 'payee');
    }

    public function scopeStandard($query)
    {
        return $query->where('type_ordonnance', 'standard');
    }

    public function scopeImpot($query)
    {
        return $query->where('type_ordonnance', 'impot');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS & MUTATEURS
    |--------------------------------------------------------------------------
    */

    /**
     * ✅ AJOUT : Obtenir le bon de commande via l'engagement
     */
    public function getBonCommandeAttribute()
    {
        if (!$this->engagement) {
            return null;
        }

        // Charger la relation engageable si pas déjà chargée
        if (!$this->engagement->relationLoaded('engageable')) {
            $this->engagement->load('engageable');
        }

        // Si l'engagement est lié à un BC
        if ($this->engagement->engageable_type === BonCommande::class) {
            return $this->engagement->engageable;
        }

        return null;
    }

    /**
     * ✅ AJOUT : Calculer le montant total des impôts
     */
    public function calculerMontantTotalImpots(): float
    {
        // Si les montants sont déjà dans l'ordonnance, les utiliser
        if ($this->montant_impot > 0) {
            return (float) $this->montant_impot;
        }

        // Sinon calculer à partir des composants
        $total = ($this->montant_tva ?? 0)
            + ($this->montant_ir ?? 0)
            + ($this->montant_tsr ?? 0)
            + ($this->montant_cnps ?? 0)
            + ($this->montant_irnc ?? 0)
            + ($this->montant_autres_taxes ?? 0);

        // Si toujours zéro, essayer depuis le BC
        if ($total == 0) {
            $bonCommande = $this->bonCommande;
            if ($bonCommande && method_exists($bonCommande, 'calculerMontantTotalImpots')) {
                return $bonCommande->calculerMontantTotalImpots();
            }
        }

        return $total;
    }

    /**
     * ✅ AJOUT : Obtenir le détail des impôts
     */
    public function getDetailImpots(): array
    {
        $bonCommande = $this->bonCommande;

        // Si on a un BC, utiliser ses montants (source de vérité)
        if ($bonCommande) {
            return [
                'tva' => (float) ($bonCommande->montant_tva ?? 0),
                'ir' => (float) ($bonCommande->montant_ir ?? 0),
                'tsr' => (float) ($bonCommande->montant_tsr ?? 0),
                'cnps' => (float) ($bonCommande->montant_cnps ?? 0),
                'irnc' => (float) ($bonCommande->montant_irnc ?? 0),
                'autres' => (float) ($bonCommande->montant_autres_taxes ?? 0),
                'total' => $bonCommande->calculerMontantTotalImpots(),
            ];
        }

        // Sinon utiliser les montants de l'ordonnance
        return [
            'tva' => (float) ($this->montant_tva ?? 0),
            'ir' => (float) ($this->montant_ir ?? 0),
            'tsr' => (float) ($this->montant_tsr ?? 0),
            'cnps' => (float) ($this->montant_cnps ?? 0),
            'irnc' => (float) ($this->montant_irnc ?? 0),
            'autres' => (float) ($this->montant_autres_taxes ?? 0),
            'total' => $this->calculerMontantTotalImpots(),
        ];
    }

    /**
     * ✅ AJOUT : Vérifier si l'ordonnance a des impôts
     */
    public function hasImpots(): bool
    {
        return $this->calculerMontantTotalImpots() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTHODES
    |--------------------------------------------------------------------------
    */

    /**
     * Générer un numéro d'OP basé sur le numéro d'engagement
     */
    // public static function genererNumeroFromEngagement(Engagement $engagement, string $type = 'standard'): string
    // {
    //     // Extraire les parties du numéro d'engagement (ex: BE-2026-00004)
    //     $parts = explode('-', $engagement->reference_document ?? $engagement->numero);

    //     if (count($parts) >= 3) {
    //         $annee = $parts[1];
    //         $numero = $parts[2];

    //         $prefix = $type === 'impot' ? 'OPT' : 'OP';

    //         return "{$prefix}-{$annee}-{$numero}";
    //     }

    //     // Fallback si le format est différent
    //     return static::genererNumero($type);
    // }

    /**
     * Générer un numéro d'OP classique (fallback)
     */
    public static function genererNumero(string $type = 'standard'): string
    {
        $year = now()->year;
        $prefix = $type === 'impot' ? 'OPT' : 'OP';

        $lastOp = static::where('numero', 'like', "{$prefix}-{$year}-%")
            ->latest('id')
            ->first();

        $numero = $lastOp
            ? ((int) substr($lastOp->numero, -5)) + 1
            : 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $numero);
    }

    /**
     * ✅ Générer un numéro d'OP basé sur le numéro du BON DE COMMANDE
     * BC-2025-001 → OP-2025-001 (standard) ou OPT-2025-001 (impôt)
     */
    public static function genererNumeroFromBonCommande(BonCommande $bonCommande, string $type = 'standard'): string
    {
        // Extraire les parties du numéro de BC (ex: BC-2025-001)
        $parts = explode('-', $bonCommande->numero);

        if (count($parts) >= 3) {
            $annee = $parts[1];
            $numero = $parts[2];

            $prefix = $type === 'impot' ? 'OPT' : 'OP';

            return "{$prefix}-{$annee}-{$numero}";
        }

        // Fallback si le format est différent
        return static::genererNumero($type);
    }

    /**
     * ✅ AJOUT : Créer une OP Impôt depuis un Bon de Commande
     */
    public static function creerDepuisBonCommande(BonCommande $bonCommande, string $type = 'standard'): self
    {
        // ✅ Charger l'engagement via la relation polymorphique
        if (!$bonCommande->relationLoaded('engagement')) {
            $bonCommande->load('engagement');
        }

        $engagement = $bonCommande->engagement;

        if (!$engagement) {
            throw new \Exception("Le bon de commande n'a pas d'engagement associé. Veuillez d'abord engager le BC.");
        }

        if ($type === 'impot') {
            // OP Impôt : pour reverser les taxes
            $montantTotalImpots = $bonCommande->calculerMontantTotalImpots();

            if ($montantTotalImpots <= 0) {
                throw new \Exception("Aucun impôt à reverser pour ce bon de commande");
            }

            return static::create([
                'exercice_id' => $bonCommande->exercice_id,
                'numero' => static::genererNumeroFromBonCommande($bonCommande, 'impot'), // ✅ Basé sur BC
                'type_ordonnance' => 'impot',
                'engagement_id' => $engagement->id,
                'beneficiaire_type' => 'App\Models\OrganismePublic',
                'beneficiaire_id' => 1,
                'date_emission' => now(),
                'objet' => "Reversement des impots et taxes - BC N° {$bonCommande->numero}",
                'montant_brut' => $montantTotalImpots,
                'montant_tva' => $bonCommande->montant_tva ?? 0,
                'montant_ir' => $bonCommande->montant_ir ?? 0,
                'montant_tsr' => $bonCommande->montant_tsr ?? 0,
                'montant_cnps' => $bonCommande->montant_cnps ?? 0,
                'montant_irnc' => $bonCommande->montant_irnc ?? 0,
                'montant_autres_taxes' => $bonCommande->montant_autres_taxes ?? 0,
                'montant_impot' => $montantTotalImpots,
                'montant_net' => $montantTotalImpots,
                'statut' => 'brouillon',
                'created_by' => auth()->id(),
            ]);
        } else {
            // OP Standard : pour payer le fournisseur (HT - IR)
            $montantNet = $bonCommande->montant_ht - $bonCommande->montant_ir;

            if ($montantNet <= 0) {
                throw new \Exception("Le montant net à payer au fournisseur est invalide");
            }

            return static::create([
                'exercice_id' => $bonCommande->exercice_id,
                'numero' => static::genererNumeroFromBonCommande($bonCommande, 'standard'), // ✅ Basé sur BC
                'type_ordonnance' => 'standard',
                'engagement_id' => $engagement->id,
                'beneficiaire_type' => Fournisseur::class,
                'beneficiaire_id' => $bonCommande->fournisseur_id,
                'date_emission' => now(),
                'objet' => "Paiement fournisseur - BC N° {$bonCommande->numero}",
                'montant_brut' => $bonCommande->montant_ht,
                'montant_ir' => $bonCommande->montant_ir ?? 0,
                'montant_impot' => $bonCommande->montant_ir ?? 0,
                'montant_net' => $montantNet,
                'statut' => 'brouillon',
                'created_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Émettre l'ordonnance
     */
    public function emettre(): void
    {
        $this->statut = 'emise';
        $this->save();
    }

    /**
     * Marquer comme payée
     */
    public function marquerPayee(string $referencePaiement = null): void
    {
        $this->statut = 'payee';
        $this->date_paiement = now();
        $this->reference_paiement = $referencePaiement;
        $this->save();
    }

    /**
     * Annuler l'ordonnance
     */
    public function annuler(): void
    {
        $this->statut = 'annulee';
        $this->save();
    }

    /**
     * Obtenir le label du statut
     */
    public function getStatutLabelAttribute(): string
    {
        return match ($this->statut) {
            'brouillon' => 'Brouillon',
            'emise' => 'Émise',
            'visee' => 'Visée',
            'payee' => 'Payée',
            'annulee' => 'Annulée',
            default => $this->statut,
        };
    }

    /**
     * Obtenir la couleur du statut
     */
    public function getStatutColorAttribute(): string
    {
        return match ($this->statut) {
            'brouillon' => 'gray',
            'emise' => 'info',
            'visee' => 'warning',
            'payee' => 'success',
            'annulee' => 'danger',
            default => 'secondary',
        };
    }
}
