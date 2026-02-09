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
        'beneficiaire_fournisseur_id',
        'beneficiaire_personnel_id',
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

    public function beneficiaire()
    {
        return $this->morphTo('beneficiaire', 'beneficiaire_type', 'beneficiaire_id');
    }

    public function getBeneficiaire()
    {
        if ($this->beneficiaire_type === 'fournisseur') {
            return $this->beneficiaireFournisseur;
        }

        if ($this->beneficiaire_type === 'personnel') {
            return $this->beneficiairePersonnel;
        }

        return null;
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
        return $this->belongsTo(Personnel::class, 'beneficiaire_personnel_id');
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
        return $this->statut === 'definitif'
            && !$this->hasOrdonnancesPaiement();
    }

    /**
     * Vérifier si peut voir les OP
     */
    public function peutVoirOP(): bool
    {
        return $this->statut === 'definitif'
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
    public function getNomBeneficiaire(): ?string
    {
        // ✅ Essayer d'abord la relation polymorphique (ancienne structure)
        if ($this->beneficiaire_id && $this->beneficiaire_type) {
            $beneficiaire = $this->beneficiaire;

            if ($beneficiaire instanceof \App\Models\Fournisseur) {
                return $beneficiaire->raison_sociale;
            }

            if ($beneficiaire instanceof \App\Models\User || $beneficiaire instanceof \App\Models\Personnel) {
                return $beneficiaire->name;
            }
        }

        // ✅ Sinon essayer les colonnes spécifiques (nouvelle structure)
        if ($this->beneficiaire_fournisseur_id && $this->beneficiaireFournisseur) {
            return $this->beneficiaireFournisseur->raison_sociale;
        }

        if ($this->beneficiaire_personnel_id && $this->beneficiairePersonnel) {
            return $this->beneficiairePersonnel->name;
        }

        return null;
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
            // ===== RÉCUPÉRATION DES MONTANTS =====
            $donnees = $this->extraireDonneesDocument();

            if (!$donnees['beneficiaire']) {
                throw new \Exception("Aucun bénéficiaire défini pour cet engagement.");
            }

            if ($donnees['montant_net'] <= 0) {
                throw new \Exception("Le montant net à payer est invalide (montant: {$donnees['montant_net']}).");
            }

            $ordonnances = [];

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
                'montant_net' => round($donnees['montant_net'], 2),
                'objet' => $this->objet,
                'reference_engagement' => $this->numero,
                'statut' => 'emise',
                'created_by' => auth()->id(),
            ]);

            $ordonnances['standard'] = $opStandard;

            \Log::info("OP Standard créée", [
                'numero' => $opStandard->numero,
                'montant' => $opStandard->montant_ordonnance,
                'beneficiaire' => $donnees['beneficiaire']->raison_sociale ?? $donnees['beneficiaire']->name ?? 'N/A',
            ]);

            // ===== 2. CRÉER L'OP IMPÔT (TOTAL DE TOUTES LES RETENUES) =====
            // ✅ Calculer le total des retenues
            $totalRetenues = 0;
            $detailsRetenues = [];

            if ($this->estBonCommande()) {
                // ✅ Pour BC : IR + TVA + TSR
                $totalRetenues = $donnees['montant_ir']
                    + $donnees['montant_tva']
                    + $donnees['montant_tsr'];

                if ($donnees['montant_ir'] > 0) {
                    $detailsRetenues[] = "IR: " . number_format($donnees['montant_ir'], 0, ',', ' ') . " FCFA";
                }
                if ($donnees['montant_tva'] > 0) {
                    $detailsRetenues[] = "TVA: " . number_format($donnees['montant_tva'], 0, ',', ' ') . " FCFA";
                }
                if ($donnees['montant_tsr'] > 0) {
                    $detailsRetenues[] = "TSR: " . number_format($donnees['montant_tsr'], 0, ',', ' ') . " FCFA";
                }
            } else {
                // ✅ Pour DA et autres : IR + CNPS + IRNC + Autres
                $totalRetenues = $donnees['montant_ir']
                    + $donnees['montant_cnps']
                    + $donnees['montant_irnc']
                    + $donnees['autres_retenues'];

                if ($donnees['montant_ir'] > 0) {
                    $detailsRetenues[] = "IR: " . number_format($donnees['montant_ir'], 0, ',', ' ') . " FCFA";
                }
                if ($donnees['montant_cnps'] > 0) {
                    $detailsRetenues[] = "CNPS: " . number_format($donnees['montant_cnps'], 0, ',', ' ') . " FCFA";
                }
                if ($donnees['montant_irnc'] > 0) {
                    $detailsRetenues[] = "IRNC: " . number_format($donnees['montant_irnc'], 0, ',', ' ') . " FCFA";
                }
                if ($donnees['autres_retenues'] > 0) {
                    $detailsRetenues[] = "Autres: " . number_format($donnees['autres_retenues'], 0, ',', ' ') . " FCFA";
                }
            }

            if ($totalRetenues > 0) {
                $tresorPublic = \App\Models\Fournisseur::firstOrCreate(
                    ['code' => 'TRESOR_PUBLIC'],
                    [
                        'raison_sociale' => 'Trésor Public',
                        'type_fournisseur' => 'administration',
                        'actif' => true,
                    ]
                );

                $objetImpot = "Retenues et Impôts - {$this->objet}";
                if (!empty($detailsRetenues)) {
                    $objetImpot .= " (" . implode(", ", $detailsRetenues) . ")";
                }

                $opImpot = \App\Models\OrdonnancePaiement::create([
                    'numero' => \App\Models\OrdonnancePaiement::genererNumero('impot'),
                    'type_ordonnance' => 'impot',
                    'engagement_id' => $this->id,
                    'ordonnance_parent_id' => $opStandard->id,
                    'exercice_id' => $this->exercice_id,
                    'budget_id' => $this->budget_id,
                    'beneficiaire_type' => 'App\Models\Fournisseur',
                    'beneficiaire_id' => $tresorPublic->id,
                    'date_emission' => now(),
                    'montant_net' => round($totalRetenues, 2),
                    'objet' => $objetImpot,
                    'reference_engagement' => $this->numero,
                    'statut' => 'emise',
                    'created_by' => auth()->id(),
                ]);

                $ordonnances['impot'] = $opImpot;

                \Log::info("OP Impôt créée", [
                    'type_document' => $this->estBonCommande() ? 'BC' : 'DA',
                    'numero' => $opImpot->numero,
                    'montant_total' => $opImpot->montant_ordonnance,
                    'detail_ir' => $donnees['montant_ir'],
                    'detail_tva' => $donnees['montant_tva'] ?? 0,
                    'detail_tsr' => $donnees['montant_tsr'] ?? 0,
                    'detail_cnps' => $donnees['montant_cnps'] ?? 0,
                    'detail_irnc' => $donnees['montant_irnc'] ?? 0,
                ]);
            }

            return $ordonnances;
        });
    }

    /**
     * ✅ EXTRAIRE LES DONNÉES DU DOCUMENT SOURCE (BC, DA, ou manuel)
     */
    public function extraireDonneesDocument(): array
    {
        $montantBrut = 0;
        $montantTVA = 0;
        $montantTSR = 0;      // ✅ Ajouter TSR
        $montantTTC = 0;
        $montantIR = 0;
        $montantCNPS = 0;
        $montantIRNC = 0;
        $autresRetenues = 0;
        $montantNet = 0;
        $beneficiaire = null;
        $beneficiaireType = null;

        // ===== CAS 1 : BON DE COMMANDE =====
        if ($this->estBonCommande() && $this->engageable) {
            $bc = $this->engageable;

            $montantBrut = $bc->montant_ht ?? 0;
            $montantTVA = $bc->montant_tva ?? 0;
            $montantTSR = $bc->montant_tsr ?? 0;      // ✅ Extraire TSR
            $montantTTC = $bc->montant_ttc ?? 0;
            $montantIR = $bc->montant_ir ?? 0;

            // ✅ Pour BC : Net = TTC - (IR + TVA + TSR)
            // Car TVA et TSR sont reversées au Trésor Public
            $montantNet = $montantTTC - ($montantIR + $montantTVA + $montantTSR);

            $beneficiaire = $bc->fournisseur;
            $beneficiaireType = 'App\Models\Fournisseur';

            \Log::info("BC - Montants extraits", [
                'bc_numero' => $bc->numero,
                'montant_ht' => $montantBrut,
                'montant_tva' => $montantTVA,
                'montant_tsr' => $montantTSR,
                'montant_ttc' => $montantTTC,
                'montant_ir' => $montantIR,
                'montant_net' => $montantNet,
            ]);
        }

        // ===== CAS 2 : DÉCISION ADMINISTRATIVE =====
        elseif ($this->estDecision() && $this->engageable) {
            $da = $this->engageable;

            $montantBrut = $da->montant_ht ?? $da->montant_brut ?? 0;
            $montantTVA = $da->montant_tva ?? 0;
            $montantTTC = $da->montant_ttc ?? $da->montant_total ?? $this->montant_engage;
            $montantIR = $da->montant_ir ?? 0;
            $montantCNPS = $da->montant_cnps ?? 0;
            $montantIRNC = $da->montant_irnc ?? 0;
            $autresRetenues = $da->autres_retenues ?? 0;

            // ✅ Pour DA : Net = TTC - (IR + CNPS + IRNC + Autres)
            // Pas de TVA/TSR pour les DA
            $montantNet = $montantTTC - ($montantIR + $montantCNPS + $montantIRNC + $autresRetenues);

            if (isset($da->beneficiaire_type)) {
                if ($da->beneficiaire_type === 'fournisseur' || $da->beneficiaire_type === 'App\Models\Fournisseur') {
                    $beneficiaire = $da->beneficiaireFournisseur;
                    $beneficiaireType = 'App\Models\Fournisseur';
                } else {
                    $beneficiaire = $da->beneficiairePersonnel;
                    $beneficiaireType = 'App\Models\Personnel';
                }
            }

            \Log::info("DA - Montants extraits", [
                'montant_ttc' => $montantTTC,
                'montant_ir' => $montantIR,
                'montant_cnps' => $montantCNPS,
                'montant_irnc' => $montantIRNC,
                'autres_retenues' => $autresRetenues,
                'montant_net' => $montantNet,
            ]);
        }

        // ===== CAS 3 : ENGAGEMENT MANUEL =====
        else {
            $montantTTC = $this->montant_engage;
            $montantIR = $this->calculerMontantImpot();
            $montantNet = $montantTTC - $montantIR;

            if ($this->beneficiaire_type === 'fournisseur' || $this->beneficiaire_type === 'App\Models\Fournisseur') {
                $beneficiaire = $this->beneficiaireFournisseur;
                $beneficiaireType = 'App\Models\Fournisseur';
            } elseif ($this->beneficiaire_type === 'personnel' || $this->beneficiaire_type === 'App\Models\Personnel' || $this->beneficiaire_type === 'App\Models\User') {
                $beneficiaire = $this->beneficiairePersonnel;
                $beneficiaireType = 'App\Models\Personnel';
            }

            \Log::info("Manuel - Montants extraits", [
                'montant_ttc' => $montantTTC,
                'montant_ir' => $montantIR,
                'montant_net' => $montantNet,
            ]);
        }

        return [
            'montant_brut' => $montantBrut,
            'montant_tva' => $montantTVA,
            'montant_tsr' => $montantTSR,
            'montant_ttc' => $montantTTC,
            'montant_ir' => $montantIR,
            'montant_cnps' => $montantCNPS,
            'montant_irnc' => $montantIRNC,
            'autres_retenues' => $autresRetenues,
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
