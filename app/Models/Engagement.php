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
     * Relation : Lignes de bordereau
     */
    public function lignesBordereau(): HasMany
    {
        return $this->hasMany(BordereauEngagementLigne::class, 'engagement_id');
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
        return $this->morphTo('engageable', 'engageable_type', 'engageable_id');
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
        return $this->engageable_type === 'bon_commande'
            || str_contains($this->engageable_type ?? '', 'BonCommande');
    }

    /**
     * ✅ Vérifier si l'engagement est lié à une Décision
     */
    public function estDecision(): bool
    {
        return $this->engageable_type === 'decision_administrative'
            || str_contains($this->engageable_type ?? '', 'DecisionAdministrative');
    }

    // public function beneficiaire(): MorphTo
    // {
    //     return $this->morphTo()->withTrashed();
    // }

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
    public static function genererNumero(): string
    {
        $annee = now()->year;
        $anneeCourtе = substr($annee, -2);

        $prefixe = "BE{$anneeCourtе}-";

        // Trouver le dernier numéro de l'année
        $dernier = static::where('numero', 'like', "{$prefixe}%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier && preg_match('/BE\d{2}-(\d+)/', $dernier->numero, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('BE%s-%05d', $anneeCourtе, $sequence);
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
                return $beneficiaire->nom_complet;
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
                'numero_emission' => \App\Models\OrdonnancePaiement::genererNumeroEmission(),
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
                'montant' => $opStandard->montant_net,  // ✅ Correction : montant_net au lieu de montant_ordonnance
                'beneficiaire' => $donnees['beneficiaire']->raison_sociale ?? $donnees['beneficiaire']->name ?? 'N/A',
            ]);

            // ===== 2. CRÉER L'OP IMPÔT (TOTAL DE TOUTES LES RETENUES) =====
            $totalRetenues = 0;
            $detailsRetenues = [];

            if ($this->estBonCommande()) {
                // ✅ Pour BC : IR + TVA + TSR (avec vérification null)
                $totalRetenues = ($donnees['montant_ir'] ?? 0)
                    + ($donnees['montant_tva'] ?? 0)
                    + ($donnees['montant_tsr'] ?? 0);

                if (($donnees['montant_ir'] ?? 0) > 0) {
                    $detailsRetenues[] = "IR: " . number_format($donnees['montant_ir'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['montant_tva'] ?? 0) > 0) {
                    $detailsRetenues[] = "TVA: " . number_format($donnees['montant_tva'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['montant_tsr'] ?? 0) > 0) {
                    $detailsRetenues[] = "TSR: " . number_format($donnees['montant_tsr'], 0, ',', ' ') . " FCFA";
                }
            } else {
                // ✅ Pour DA et autres : IR + CNPS + IRNC + Autres (avec vérification null)
                $totalRetenues = ($donnees['montant_ir'] ?? 0)
                    + ($donnees['montant_cnps'] ?? 0)
                    + ($donnees['montant_irnc'] ?? 0)
                    + ($donnees['montant_tva'] ?? 0)
                    + ($donnees['montant_redevance'] ?? 0)
                    + ($donnees['montant_feicom'] ?? 0)
                    + ($donnees['autres_retenues'] ?? 0);

                if (($donnees['montant_tva'] ?? 0) > 0) {
                    $detailsRetenues[] = "TVA: " . number_format($donnees['montant_tva'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['montant_redevance'] ?? 0) > 0) {
                    $detailsRetenues[] = "Redevance audiovisuelle: " . number_format($donnees['montant_redevance'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['montant_feicom'] ?? 0) > 0) {
                    $detailsRetenues[] = "FEICOM: " . number_format($donnees['montant_feicom'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['montant_ir'] ?? 0) > 0) {
                    $detailsRetenues[] = "IR: " . number_format($donnees['montant_ir'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['montant_cnps'] ?? 0) > 0) {
                    $detailsRetenues[] = "CNPS: " . number_format($donnees['montant_cnps'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['montant_irnc'] ?? 0) > 0) {
                    $detailsRetenues[] = "IRNC: " . number_format($donnees['montant_irnc'], 0, ',', ' ') . " FCFA";
                }
                if (($donnees['autres_retenues'] ?? 0) > 0) {
                    $detailsRetenues[] = "Autres: " . number_format($donnees['autres_retenues'], 0, ',', ' ') . " FCFA";
                }
            }

            \Log::info("Calcul total retenues", [
                'type_document' => $this->estBonCommande() ? 'BC' : 'DA',
                'montant_ir' => $donnees['montant_ir'] ?? 0,
                'montant_cnps' => $donnees['montant_cnps'] ?? 0,
                'montant_irnc' => $donnees['montant_irnc'] ?? 0,
                'montant_tva' => $donnees['montant_tva'] ?? 0,
                'montant_redevance' => $donnees['montant_redevance'] ?? 0,
                'montant_feicom' => $donnees['montant_feicom'] ?? 0,
                'montant_tsr' => $donnees['montant_tsr'] ?? 0,
                'autres_retenues' => $donnees['autres_retenues'] ?? 0,
                'total_retenues' => $totalRetenues,
                'details' => $detailsRetenues,
            ]);

            // ✅ Créer l'OP Impôt si le total > 0
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
                    'numero_emission' => \App\Models\OrdonnancePaiement::genererNumeroEmission(),
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

                \Log::info("OP Impôt créée avec succès", [
                    'type_document' => $this->estBonCommande() ? 'BC' : 'DA',
                    'numero' => $opImpot->numero,
                    'montant_total' => $opImpot->montant_net,
                    'detail_ir' => $donnees['montant_ir'] ?? 0,
                    'detail_cnps' => $donnees['montant_cnps'] ?? 0,
                    'detail_irnc' => $donnees['montant_irnc'] ?? 0,
                    'detail_tva' => $donnees['montant_tva'] ?? 0,
                    'detail_redevance' => $donnees['montant_redevance'] ?? 0,
                    'detail_feicom' => $donnees['montant_feicom'] ?? 0,
                    'detail_tsr' => $donnees['montant_tsr'] ?? 0,
                    'detail_autres' => $donnees['autres_retenues'] ?? 0,
                ]);
            } else {
                \Log::info("Pas d'OP Impôt créée", [
                    'raison' => 'Aucune retenue (total = 0)',
                    'type_document' => $this->estBonCommande() ? 'BC' : 'DA',
                ]);
            }

            // ✅ Vérification finale
            $totalOP = $opStandard->montant_net + ($ordonnances['impot']->montant_net ?? 0);

            \Log::info("✅ Ordonnances créées avec succès", [
                'engagement_numero' => $this->numero,
                'montant_ttc_engagement' => $donnees['montant_ttc'],
                'op_standard' => $opStandard->montant_net,
                'op_impot' => $ordonnances['impot']->montant_net ?? 0,
                'total_ordonnances' => $totalOP,
                'difference' => abs($donnees['montant_ttc'] - $totalOP),
            ]);

            // Alerte si écart
            if (abs($donnees['montant_ttc'] - $totalOP) > 0.01) {
                \Log::warning("⚠️ Écart détecté entre TTC et total OP", [
                    'ttc' => $donnees['montant_ttc'],
                    'total_op' => $totalOP,
                    'ecart' => $donnees['montant_ttc'] - $totalOP,
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
        $montantHT = 0;
        $montantBrut = 0;
        $montantTVA = 0;
        $montantTSR = 0;
        $montantTTC = 0;
        $montantIR = 0;
        $montantCNPS = 0;
        $montantIRNC = 0;
        $montantRedevance = 0;
        $montantFeicom = 0;
        $autresRetenues = 0;
        $montantNet = 0;
        $beneficiaire = null;
        $beneficiaireType = null;

        // ===== CAS 1 : BON DE COMMANDE =====
        if ($this->estBonCommande() && $this->engageable) {
            $bc = $this->engageable;

            $montantHT = $bc->montant_ht ?? 0;
            $montantBrut = $bc->montant_ttc ?? 0;
            $montantTVA = $bc->montant_tva ?? 0;
            $montantTSR = $bc->montant_tsr ?? 0;
            $montantTTC = $bc->montant_ttc ?? 0;
            $montantIR = $bc->montant_ir ?? 0;
            $montantNet = $montantTTC - ($montantIR + $montantTVA + $montantTSR);

            // Charger et récupérer le fournisseur
            $bc->load('fournisseur');
            $beneficiaire = $bc->fournisseur;
            $beneficiaireType = 'App\Models\Fournisseur';

            \Log::info("BC - Montants extraits", [
                'bc_numero' => $bc->numero,
                'beneficiaire' => $beneficiaire?->raison_sociale ?? 'NULL',
                'montant_ht' => $montantHT,
                'montant_ttc' => $montantTTC,
                'montant_net' => $montantNet,
            ]);
        }

        // ===== CAS 2 : DÉCISION ADMINISTRATIVE =====
        elseif ($this->estDecision() && $this->engageable) {
            $da = $this->engageable;

            $montantBrut = $da->montant_brut ?? 0;
            $montantTTC = $montantBrut;
            $montantIR = $da->montant_ir ?? 0;
            $montantCNPS = $da->montant_cnps ?? 0;
            $montantIRNC = $da->montant_irnc ?? 0;
            $montantTVA = $da->montant_tva_calcule ?? ($da->montant_tva ?? 0);
            $montantRedevance = $da->montant_redevance_audiovisuelle_calcule ?? ($da->montant_redevance_audiovisuelle ?? 0);
            $montantFeicom = $da->montant_feicom_calcule ?? ($da->montant_feicom ?? 0);
            $autresRetenues = $da->autres_retenues ?? 0;
            $montantNet = $da->montant_net ?? 0;
            // $montantNet = $montantTTC - ($montantIR + $montantCNPS + $montantIRNC + $autresRetenues);

            // ✅ RÉCUPÉRER LE BÉNÉFICIAIRE (Personnel pour une DA)
            \Log::info("DA - Récupération bénéficiaire", [
                'da_id' => $da->id,
                'personnel_id' => $da->personnel_id,
                'personnel_relation_loaded' => $da->relationLoaded('personnel'),
            ]);

            // Charger la relation si pas déjà chargée
            if (!$da->relationLoaded('personnel')) {
                $da->load('personnel');
            }

            if ($da->personnel_id && $da->personnel) {
                $beneficiaire = $da->personnel;
                $beneficiaireType = 'App\Models\Personnel';

                \Log::info("DA - Bénéficiaire trouvé", [
                    'personnel_id' => $beneficiaire->id,
                    'nom' => $beneficiaire->nom_complet,
                ]);
            } else {
                \Log::error("DA - Bénéficiaire non trouvé", [
                    'da_id' => $da->id,
                    'personnel_id' => $da->personnel_id,
                    'personnel_exists' => $da->personnel !== null,
                ]);
            }

            // ✅ CALCUL TOTAL RETENUES (pour vérification)
            $totalRetenues = $montantIR + $montantCNPS + $montantIRNC +
                $montantTVA + $montantRedevance + $montantFeicom +
                $autresRetenues;

            \Log::info("DA - Montants extraits", [
                'da_numero' => $da->numero ?? 'N/A',
                'montant_brut' => $montantBrut,
                'retenue_ir' => $montantIR,
                'retenue_cnps' => $montantCNPS,
                'retenue_irnc' => $montantIRNC,
                'retenue_tva' => $montantTVA,
                'retenue_redevance' => $montantRedevance,
                'retenue_feicom' => $montantFeicom,
                'retenue_autres' => $autresRetenues,
                'total_retenues' => $totalRetenues,
                'montant_net' => $montantNet,
                'beneficiaire' => $beneficiaire?->nom_complet ?? 'NULL',
            ]);
        }

        // ===== CAS 3 : ENGAGEMENT MANUEL =====
        else {
            $montantTTC = $this->montant_engage;
            $montantIR = $this->calculerMontantImpot();
            $montantNet = $montantTTC - $montantIR;

            // Charger le bénéficiaire selon le type
            if ($this->beneficiaire_type === 'App\Models\Fournisseur') {
                if (!$this->relationLoaded('beneficiaireFournisseur')) {
                    $this->load('beneficiaireFournisseur');
                }
                $beneficiaire = $this->beneficiaireFournisseur;
                $beneficiaireType = 'App\Models\Fournisseur';
            } elseif ($this->beneficiaire_type === 'App\Models\Personnel') {
                if (!$this->relationLoaded('beneficiairePersonnel')) {
                    $this->load('beneficiairePersonnel');
                }
                $beneficiaire = $this->beneficiairePersonnel;
                $beneficiaireType = 'App\Models\Personnel';
            }
        }

        // ✅ LOG FINAL
        \Log::info("Données extraites - Résumé final", [
            'engagement_numero' => $this->numero,
            'beneficiaire_existe' => $beneficiaire !== null,
            'beneficiaire_type' => $beneficiaireType,
            'beneficiaire_nom' => $beneficiaire?->nom_complet ?? $beneficiaire?->raison_sociale ?? 'NULL',
        ]);

        return [
            'montant_ht' => $montantHT,
            'montant_brut' => $montantBrut,
            'montant_tva' => $montantTVA,
            'montant_tsr' => $montantTSR,
            'montant_ttc' => $montantTTC,
            'montant_ir' => $montantIR,
            'montant_cnps' => $montantCNPS,
            'montant_irnc' => $montantIRNC,
            'montant_redevance' => $montantRedevance,
            'montant_feicom' => $montantFeicom,
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
