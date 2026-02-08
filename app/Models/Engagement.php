<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        'beneficiaire_fournisseur_id', // Ajouté pour compatibilité
        'beneficiaire_personnel_id',   // Ajouté pour compatibilité
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
        if (!$this->relationLoaded('engageable')) {
            $this->load('engageable');
        }

        if ($this->engageable instanceof \App\Models\BonCommande) {
            return $this->engageable;
        }

        return null;
    }

    /**
     * ✅ MÉTHODE ALTERNATIVE : Obtenir le bon de commande
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
     */
    public function beneficiaire(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relation : Bénéficiaire fournisseur (pour engagements manuels)
     */
    public function beneficiaireFournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'beneficiaire_fournisseur_id');
    }

    /**
     * Relation : Bénéficiaire personnel (pour engagements manuels)
     */
    public function beneficiairePersonnel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiaire_personnel_id');
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
     * Alias pour bordereaux()
     */
    public function bordereauEngagements(): BelongsToMany
    {
        return $this->bordereaux();
    }

    /**
     * Relation : Ordonnances de paiement
     */
    public function ordonnancesPaiement(): HasMany
    {
        return $this->hasMany(\App\Models\OrdonnancePaiement::class, 'engagement_id');
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
     * Vérifier si peut créer des ordonnances
     */
    public function peutCreerOrdonnances(): bool
    {
        return $this->statut === 'definitif';
    }

    /**
     * Vérifier si peut voir les OP
     */
    public function peutVoirOP(): bool
    {
        return in_array($this->statut, ['definitif'])
            && $this->hasOrdonnancesPaiement();
    }

    /**
     * Vérifier si peut être annulé
     */
   // Dans App\Models\Engagement.php

    /**
     * ✅ Vérifier si peut être annulé
     */
    public function peutEtreAnnule(): bool
    {
        // ✅ Ne peut pas annuler si déjà annulé
        if ($this->statut === 'annule') {
            return false;
        }

        // ✅ Ne peut pas annuler si définitif
        if ($this->statut === 'definitif') {
            return false;
        }

        // ✅ Ne peut pas annuler si soldé
        if ($this->statut === 'solde') {
            return false;
        }

        // ✅ Ne peut pas annuler s'il y a des ordonnances de paiement
        if ($this->ordonnancesPaiement()->exists()) {
            return false;
        }

        // ✅ Peut annuler seulement si provisoire et sans ordonnances
        return $this->statut === 'provisoire';
    }

    /**
     * Annuler l'engagement
     */
    public function annuler(): void
    {
        // ✅ Vérifications de sécurité
        if ($this->statut === 'definitif') {
            throw new \Exception("Impossible d'annuler un engagement définitif.");
        }

        if ($this->statut === 'annule') {
            throw new \Exception("Cet engagement est déjà annulé.");
        }

        if ($this->ordonnancesPaiement()->exists()) {
            throw new \Exception("Impossible d'annuler un engagement qui a des ordonnances de paiement.");
        }

        // Libérer les crédits engagés
        if ($this->nomenclature_principale_id) {
            $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                ->where('nomenclature_id', $this->nomenclature_principale_id)
                ->first();

            if ($ligneBudgetaire) {
                $ligneBudgetaire->engage -= $this->montant_engage;
                $ligneBudgetaire->save();
            }
        }

        // Marquer comme annulé
        $this->statut = 'annule';
        $this->date_annulation = now();
        $this->annule_par = auth()->id();
        $this->save();

        // Log
        \Log::info("Engagement {$this->numero} annulé", [
            'id' => $this->id,
            'montant' => $this->montant_engage,
            'user' => auth()->id(),
        ]);
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
            'App\Models\Fournisseur' => $this->beneficiaire->raison_sociale ?? 'N/A',
            'App\Models\User' => $this->beneficiaire->name ?? 'N/A',
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

    // ========================================================================
    // CRÉATION DES ORDONNANCES DE PAIEMENT - VERSION CORRIGÉE
    // ========================================================================

    /**
     * ✅ CRÉER LES ORDONNANCES DE PAIEMENT (Standard + Impôt)
     * Version corrigée avec les bons montants
     */
    public function creerOrdonnancesPaiement(): array
    {
        // Vérifications préalables
        if (!$this->peutCreerOrdonnances()) {
            throw new \Exception("Impossible de créer les ordonnances : l'engagement doit être au statut DÉFINITIF.");
        }

        if ($this->hasOrdonnancesPaiement()) {
            throw new \Exception("Des ordonnances existent déjà pour cet engagement.");
        }

        if ($this->montant_engage <= 0) {
            throw new \Exception("Montant d'engagement invalide.");
        }

        return DB::transaction(function () {
            // ===== RÉCUPÉRATION DES MONTANTS DEPUIS LE DOCUMENT SOURCE =====

            $donnees = $this->extraireDonneesDocument();

            if (!$donnees['beneficiaire']) {
                throw new \Exception("Aucun bénéficiaire défini pour cet engagement.");
            }

            if ($donnees['montant_net'] <= 0) {
                throw new \Exception("Le montant net à payer est invalide (montant: {$donnees['montant_net']}).");
            }

            // ===== 1. CRÉER L'OP STANDARD (Bénéficiaire principal) =====

            $opStandard = \App\Models\OrdonnancePaiement::create([
                'numero' => \App\Models\OrdonnancePaiement::genererNumero('standard'),
                'type_ordonnance' => 'standard',
                'engagement_id' => $this->id,
                'exercice_id' => $this->exercice_id,
                'budget_id' => $this->budget_id,
                'beneficiaire_type' => $donnees['beneficiaire_type'],
                'beneficiaire_id' => $donnees['beneficiaire']->id,
                'date_emission' => now(),
                'montant_ordonnance' => round($donnees['montant_net'], 2), // ✅ MONTANT NET CORRECT
                'objet' => $this->objet,
                'reference_engagement' => $this->numero,
                'statut' => 'emise', // ✅ STATUT ÉMISE (pas brouillon)
                'created_by' => auth()->id(),
            ]);

            $ordonnances = ['standard' => $opStandard];

            // ===== 2. CRÉER L'OP IMPÔT (Si IR > 0) =====

            if ($donnees['montant_ir'] > 0) {
                // Trouver ou créer le bénéficiaire "DGI"
                $tresorPublic = \App\Models\Fournisseur::firstOrCreate(
                    ['code' => 'DGI'],
                    [
                        'raison_sociale' => 'Direction Générale des Impôts',
                        'type_fournisseur' => 'administration',
                        'actif' => true,
                    ]
                );

                $opImpot = \App\Models\OrdonnancePaiement::create([
                    'numero' => \App\Models\OrdonnancePaiement::genererNumero('impot'),
                    'type_ordonnance' => 'impot',
                    'engagement_id' => $this->id,
                    'ordonnance_parent_id' => $opStandard->id, // Lien avec l'OP standard
                    'exercice_id' => $this->exercice_id,
                    'budget_id' => $this->budget_id,
                    'beneficiaire_type' => 'App\Models\Fournisseur',
                    'beneficiaire_id' => $tresorPublic->id,
                    'date_emission' => now(),
                    'montant_ordonnance' => round($donnees['montant_ir'], 2), // ✅ MONTANT IR CORRECT
                    'objet' => "Impôt sur revenu (IR) - {$this->objet}",
                    'reference_engagement' => $this->numero,
                    'statut' => 'emise', // ✅ STATUT ÉMISE
                    'created_by' => auth()->id(),
                ]);

                $ordonnances['impot'] = $opImpot;
            }

            return $ordonnances;
        });
    }

    /**
     * ✅ EXTRAIRE LES DONNÉES DU DOCUMENT SOURCE (BC, DA, ou manuel)
     */
    protected function extraireDonneesDocument(): array
    {
        $montantTotal = 0;
        $montantIR = 0;
        $montantNet = 0;
        $beneficiaire = null;
        $beneficiaireType = null;

        // CAS 1 : BON DE COMMANDE
        if ($this->estBonCommande() && $this->engageable) {
            $bc = $this->engageable;

            $montantTotal = $bc->montant_ttc ?? 0;
            $montantIR = $bc->montant_ir ?? 0;
            $montantNet = $bc->net_a_percevoir ?? ($montantTotal - $montantIR);
            $beneficiaire = $bc->fournisseur;
            $beneficiaireType = 'App\Models\Fournisseur';
        }
        // CAS 2 : DÉCISION ADMINISTRATIVE
        elseif ($this->estDecision() && $this->engageable) {
            $da = $this->engageable;

            $montantTotal = $da->montant_total ?? $da->montant_ttc ?? $this->montant_engage;
            $montantIR = $da->montant_ir ?? 0;
            $montantNet = $da->net_a_percevoir ?? ($montantTotal - $montantIR);

            // Bénéficiaire peut être un fournisseur ou un personnel
            if (isset($da->beneficiaire_type)) {
                if ($da->beneficiaire_type === 'fournisseur') {
                    $beneficiaire = $da->beneficiaireFournisseur;
                    $beneficiaireType = 'App\Models\Fournisseur';
                } else {
                    $beneficiaire = $da->beneficiairePersonnel;
                    $beneficiaireType = 'App\Models\User';
                }
            }
        }
        // CAS 3 : ENGAGEMENT MANUEL (sans document source)
        else {
            $montantTotal = $this->montant_engage;

            // Calculer l'IR automatiquement
            $montantIR = $this->calculerMontantImpot();
            $montantNet = $montantTotal - $montantIR;

            // Déterminer le bénéficiaire depuis les champs de l'engagement
            if ($this->beneficiaire_type === 'fournisseur' || $this->beneficiaire_type === 'App\Models\Fournisseur') {
                $beneficiaire = $this->beneficiaireFournisseur ?? $this->beneficiaire;
                $beneficiaireType = 'App\Models\Fournisseur';
            } elseif ($this->beneficiaire_type === 'personnel' || $this->beneficiaire_type === 'App\Models\User') {
                $beneficiaire = $this->beneficiairePersonnel ?? $this->beneficiaire;
                $beneficiaireType = 'App\Models\User';
            } else {
                // Fallback sur beneficiaire polymorphique
                $beneficiaire = $this->beneficiaire;
                $beneficiaireType = $this->beneficiaire_type;
            }
        }

        return [
            'montant_total' => $montantTotal,
            'montant_ir' => $montantIR,
            'montant_net' => $montantNet,
            'beneficiaire' => $beneficiaire,
            'beneficiaire_type' => $beneficiaireType,
        ];
    }

    /**
     * ✅ CALCULER LE MONTANT IR POUR LES ENGAGEMENTS MANUELS
     */
    protected function calculerMontantImpot(): float
    {
        // Si l'engagement a un document source, ne pas recalculer
        if ($this->engageable_type && $this->engageable) {
            return 0;
        }

        $montant = $this->montant_engage;

        // Pour les fournisseurs
        if (($this->beneficiaire_type === 'fournisseur' || $this->beneficiaire_type === 'App\Models\Fournisseur')
            && $this->beneficiaireFournisseur
        ) {

            $fournisseur = $this->beneficiaireFournisseur;

            // Charger le régime fiscal si nécessaire
            if (!$fournisseur->relationLoaded('regimeFiscal')) {
                $fournisseur->load('regimeFiscal');
            }

            if ($fournisseur->regimeFiscal) {
                $tauxIR = $fournisseur->regimeFiscal->taux_ir_defaut ?? 0;
                return round(($montant * $tauxIR) / 100, 2);
            }
        }

        // Pour les agents (personnel) - Barème IR personnel simplifié
        if ($this->beneficiaire_type === 'personnel' || $this->beneficiaire_type === 'App\Models\User') {
            if ($montant < 500000) {
                return round(($montant * 5.5) / 100, 2);
            } elseif ($montant < 3000000) {
                return round(($montant * 11.0) / 100, 2);
            } else {
                return round(($montant * 15.0) / 100, 2);
            }
        }

        return 0;
    }

    /**
     * Vérifier si l'engagement a déjà des ordonnances
     */
    public function hasOrdonnancesPaiement(): bool
    {
        return $this->ordonnancesPaiement()->exists();
    }
}
