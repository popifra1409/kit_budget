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

    use HasWorkflow, HasExercice {
        HasWorkflow::estModifiable insteadof HasExercice;
        HasExercice::estModifiable as estModifiableParExercice;
    }

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
        'date_annulation',
        'annule_par',
        'observations',
    ];

    protected $casts = [
        'date_engagement' => 'date',
        'date_validation' => 'datetime',
        'montant_engage'  => 'decimal:2',
        'exercice'        => 'integer',
    ];

    // =========================================================
    // BOOT
    // =========================================================
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

        /**
         * ✅ AJOUT — Quand un engagement est supprimé (soft delete OU force delete)
         *    → BC source : statut 'valide', engage = false
         *    → DA source : statut 'validee'
         *
         * Ce hook s'exécute quel que soit le chemin de suppression :
         * action Filament, bulk delete, page Vue, tinker, annuler()...
         * La mise à jour est idempotente → safe si appelée plusieurs fois.
         */
        static::deleted(function (Engagement $engagement) {
            if (!$engagement->engageable) return;

            try {
                if ($engagement->estBonCommande()) {
                    $engagement->engageable->updateQuietly([
                        'statut' => 'valide',
                        'engage' => false,
                    ]);
                    \Illuminate\Support\Facades\Log::info('BC remis à valide après suppression engagement', [
                        'engagement' => $engagement->numero,
                        'bc'         => $engagement->engageable->numero,
                    ]);
                } elseif ($engagement->estDecision()) {
                    $engagement->engageable->updateQuietly([
                        'statut' => 'validee',
                    ]);
                    \Illuminate\Support\Facades\Log::info('DA remise à validee après suppression engagement', [
                        'engagement' => $engagement->numero,
                        'da'         => $engagement->engageable->numero,
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Erreur MAJ document source après delete engagement', [
                    'engagement' => $engagement->numero,
                    'error'      => $e->getMessage(),
                ]);
            }
        });

        /**
         * ✅ AJOUT — Quand un engagement soft-deleted est restauré
         *    → BC source : statut 'engage', engage = true
         *    → DA source : statut 'engagee'
         */
        static::restored(function (Engagement $engagement) {
            if (!$engagement->engageable) return;

            try {
                if ($engagement->estBonCommande()) {
                    $engagement->engageable->updateQuietly([
                        'statut' => 'engage',
                        'engage' => true,
                    ]);
                } elseif ($engagement->estDecision()) {
                    $engagement->engageable->updateQuietly([
                        'statut' => 'engagee',
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Erreur MAJ document source après restore engagement', [
                    'engagement' => $engagement->numero,
                    'error'      => $e->getMessage(),
                ]);
            }
        });
    }

    // =========================================================
    // RELATIONS
    // =========================================================
    public function lignesBordereau(): HasMany
    {
        return $this->hasMany(BordereauEngagementLigne::class, 'engagement_id');
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function nomenclaturePrincipale(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_principale_id');
    }

    public function engageable(): MorphTo
    {
        return $this->morphTo('engageable', 'engageable_type', 'engageable_id');
    }

    public function getBonCommandeAttribute()
    {
        if (!$this->relationLoaded('engageable')) {
            $this->load('engageable');
        }
        return $this->engageable instanceof \App\Models\BonCommande ? $this->engageable : null;
    }

    public function obtenirBonCommande(): ?\App\Models\BonCommande
    {
        $this->loadMissing('engageable');
        return $this->engageable instanceof \App\Models\BonCommande ? $this->engageable : null;
    }

    public function estBonCommande(): bool
    {
        return $this->engageable_type === 'bon_commande'
            || str_contains($this->engageable_type ?? '', 'BonCommande');
    }

    public function estDecision(): bool
    {
        return $this->engageable_type === 'decision_administrative'
            || str_contains($this->engageable_type ?? '', 'DecisionAdministrative');
    }

    public function beneficiaire()
    {
        return $this->morphTo('beneficiaire', 'beneficiaire_type', 'beneficiaire_id');
    }

    public function getBeneficiaire()
    {
        if ($this->beneficiaire_type === 'fournisseur') return $this->beneficiaireFournisseur;
        if ($this->beneficiaire_type === 'personnel')   return $this->beneficiairePersonnel;
        return null;
    }

    public function beneficiaireFournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'beneficiaire_fournisseur_id');
    }

    public function beneficiairePersonnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'beneficiaire_personnel_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneEngagement::class);
    }

    public function engagePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engage_par');
    }

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

    public function bordereauEngagements(): BelongsToMany
    {
        return $this->bordereaux();
    }

    public function ordonnancesPaiement(): HasMany
    {
        return $this->hasMany(\App\Models\OrdonnancePaiement::class, 'engagement_id');
    }

    // =========================================================
    // SCOPES
    // =========================================================
    public function scopeExercice($query, $exercice)
    {
        return $query->where('exercice', $exercice);
    }
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }
    public function scopeType($query, $type)
    {
        return $query->where('engageable_type', $type);
    }

    // =========================================================
    // NUMÉROTATION
    // =========================================================
    public static function genererNumero(): string
    {
        $anneeCourte = substr(now()->year, -2);
        $prefixe     = "BE{$anneeCourte}-";

        return DB::transaction(function () use ($anneeCourte, $prefixe) {
            // lockForUpdate() + first() (LIMIT 1) est compatible PostgreSQL
            // (seul COUNT/SUM/MAX avec FOR UPDATE est interdit)
            $dernier = static::withTrashed()
                ->where('numero', 'like', "{$prefixe}%")
                ->lockForUpdate()
                ->orderBy('numero', 'desc')
                ->first();

            $sequence = 1;
            if ($dernier && preg_match('/BE\d{2}-(\d+)/', $dernier->numero, $matches)) {
                $sequence = intval($matches[1]) + 1;
            }

            return sprintf('BE%s-%05d', $anneeCourte, $sequence);
        });
    }

    // =========================================================
    // MÉTHODES MÉTIER
    // =========================================================
    public function passerDefinitif(User $user): void
    {
        $this->statut          = 'definitif';
        $this->engage_par      = $user->id;
        $this->date_validation = now();
        $this->save();
    }

    public function peutCreerOrdonnances(): bool
    {
        return $this->statut === 'definitif' && !$this->hasOrdonnancesPaiement();
    }

    public function peutVoirOP(): bool
    {
        return $this->statut === 'definitif' && $this->hasOrdonnancesPaiement();
    }

    public function peutEtreAnnule(): bool
    {
        if ($this->statut === 'annule')   return false;
        if ($this->statut === 'definitif') return false;
        if ($this->statut === 'solde')    return false;
        if ($this->ordonnancesPaiement()->exists()) return false;
        return $this->statut === 'provisoire';
    }

    /**
     * Annuler l'engagement
     *
     * ✅ NOTE : le hook static::deleted() gère la mise à jour DA/BC
     *    automatiquement lors du forceDelete() ci-dessous.
     *    L'EngagementResource action fait également la mise à jour
     *    en aval (double appel idempotent = sans risque).
     */
    public function annuler(bool $force = false): void
    {
        if ($this->statut === 'annule') {
            throw new \Exception("Cet engagement est déjà annulé.");
        }

        if (!$force && $this->statut === 'definitif') {
            throw new \Exception("Impossible d'annuler un engagement définitif.");
        }

        if ($this->ordonnancesPaiement()->exists()) {
            throw new \Exception(
                "Impossible d'annuler : des ordonnances de paiement existent sur cet engagement."
            );
        }

        DB::beginTransaction();
        try {
            // Libérer les crédits via les lignes d'engagement
            foreach ($this->lignes as $ligne) {
                $lb = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $ligne->nomenclature_id)
                    ->first();

                if ($lb && $lb->engage > 0) {
                    $lb->engage = max(0, $lb->engage - $ligne->montant);
                    $lb->save();
                    \Log::info("Crédit libéré via Engagement::annuler()", [
                        'nomenclature_id' => $ligne->nomenclature_id,
                        'montant'         => $ligne->montant,
                    ]);
                }
            }

            // Fallback — si pas de lignes
            if ($this->lignes->isEmpty() && $this->nomenclature_principale_id) {
                $lb = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $this->nomenclature_principale_id)
                    ->first();
                if ($lb && $lb->engage > 0) {
                    $lb->engage = max(0, $lb->engage - $this->montant_engage);
                    $lb->save();
                }
            }

            \Log::info("Engagement {$this->numero} — crédits libérés — suppression définitive");

            // ✅ Le hook static::deleted() s'exécute lors de forceDelete()
            //    et met à jour DA/BC automatiquement
            $this->lignes()->delete();
            $this->forceDelete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Erreur annulation engagement", [
                'engagement_id' => $this->id,
                'erreur'        => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function solder(): void
    {
        if ($this->statut !== 'definitif') {
            throw new \Exception("Seul un engagement définitif peut être soldé");
        }
        $this->statut = 'solde';
        $this->save();
    }

    public function getTypeLabel(): string
    {
        return match ($this->engageable_type) {
            'App\Models\BonCommande'            => 'Bon de Commande',
            'App\Models\DecisionAdministrative' => 'Décision Administrative',
            'App\Models\Marche'                 => 'Marché',
            default                             => class_basename($this->engageable_type),
        };
    }

    public function getNomBeneficiaire(): ?string
    {
        if ($this->beneficiaire_id && $this->beneficiaire_type) {
            $beneficiaire = $this->beneficiaire;
            if ($beneficiaire instanceof \App\Models\Fournisseur) return $beneficiaire->raison_sociale;
            if ($beneficiaire instanceof \App\Models\User || $beneficiaire instanceof \App\Models\Personnel)
                return $beneficiaire->nom_complet;
        }

        if ($this->beneficiaire_fournisseur_id && $this->beneficiaireFournisseur)
            return $this->beneficiaireFournisseur->raison_sociale;

        if ($this->beneficiaire_personnel_id && $this->beneficiairePersonnel)
            return $this->beneficiairePersonnel->name;

        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'montant_engage', 'date_validation'])
            ->logOnlyDirty();
    }

    public function hasOrdonnancesPaiement(): bool
    {
        return $this->ordonnancesPaiement()->exists();
    }

    // =========================================================
    // CRÉATION DES ORDONNANCES DE PAIEMENT
    // =========================================================
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
            $donnees = $this->extraireDonneesDocument();

            if (!$donnees['beneficiaire']) {
                throw new \Exception("Aucun bénéficiaire défini pour cet engagement.");
            }
            if ($donnees['montant_net'] <= 0) {
                throw new \Exception("Le montant net à payer est invalide (montant: {$donnees['montant_net']}).");
            }

            $ordonnances = [];

            // 1. OP Standard
            $opStandard = \App\Models\OrdonnancePaiement::create([
                'numero'              => \App\Models\OrdonnancePaiement::genererNumero($this, 'standard'),
                'numero_emission'     => \App\Models\OrdonnancePaiement::genererNumeroEmission(),
                'type_ordonnance'     => 'standard',
                'engagement_id'       => $this->id,
                'exercice_id'         => $this->exercice_id,
                'budget_id'           => $this->budget_id,
                'beneficiaire_type'   => $donnees['beneficiaire_type'],
                'beneficiaire_id'     => $donnees['beneficiaire']->id,
                'date_emission'       => now(),
                'montant_net'         => round($donnees['montant_net'], 2),
                'objet'               => $this->objet,
                'reference_engagement' => $this->numero,
                'statut'              => 'emise',
                'created_by'          => auth()->id(),
            ]);
            $ordonnances['standard'] = $opStandard;

            \Log::info("OP Standard créée", [
                'numero'       => $opStandard->numero,
                'montant'      => $opStandard->montant_net,
                'beneficiaire' => $donnees['beneficiaire']->raison_sociale ?? $donnees['beneficiaire']->name ?? 'N/A',
            ]);

            // 2. OP Impôt
            $totalRetenues  = 0;
            $detailsRetenues = [];

            if ($this->estBonCommande()) {
                $totalRetenues = ($donnees['montant_ir']  ?? 0)
                    + ($donnees['montant_tva'] ?? 0)
                    + ($donnees['montant_tsr'] ?? 0);
                if (($donnees['montant_ir']  ?? 0) > 0) $detailsRetenues[] = "IR: "  . number_format($donnees['montant_ir'],  0, ',', ' ') . " FCFA";
                if (($donnees['montant_tva'] ?? 0) > 0) $detailsRetenues[] = "TVA: " . number_format($donnees['montant_tva'], 0, ',', ' ') . " FCFA";
                if (($donnees['montant_tsr'] ?? 0) > 0) $detailsRetenues[] = "TSR: " . number_format($donnees['montant_tsr'], 0, ',', ' ') . " FCFA";
            } else {
                $totalRetenues = ($donnees['montant_ir']       ?? 0)
                    + ($donnees['montant_cnps']     ?? 0)
                    + ($donnees['montant_irnc']     ?? 0)
                    + ($donnees['montant_tva']      ?? 0)
                    + ($donnees['montant_redevance'] ?? 0)
                    + ($donnees['montant_feicom']   ?? 0)
                    + ($donnees['autres_retenues']  ?? 0);
                if (($donnees['montant_tva']       ?? 0) > 0) $detailsRetenues[] = "TVA: "       . number_format($donnees['montant_tva'],       0, ',', ' ') . " FCFA";
                if (($donnees['montant_redevance']  ?? 0) > 0) $detailsRetenues[] = "Redevance: " . number_format($donnees['montant_redevance'],  0, ',', ' ') . " FCFA";
                if (($donnees['montant_feicom']    ?? 0) > 0) $detailsRetenues[] = "FEICOM: "    . number_format($donnees['montant_feicom'],    0, ',', ' ') . " FCFA";
                if (($donnees['montant_ir']        ?? 0) > 0) $detailsRetenues[] = "IR: "        . number_format($donnees['montant_ir'],        0, ',', ' ') . " FCFA";
                if (($donnees['montant_cnps']      ?? 0) > 0) $detailsRetenues[] = "CNPS: "      . number_format($donnees['montant_cnps'],      0, ',', ' ') . " FCFA";
                if (($donnees['montant_irnc']      ?? 0) > 0) $detailsRetenues[] = "IRNC: "      . number_format($donnees['montant_irnc'],      0, ',', ' ') . " FCFA";
                if (($donnees['autres_retenues']   ?? 0) > 0) $detailsRetenues[] = "Autres: "    . number_format($donnees['autres_retenues'],   0, ',', ' ') . " FCFA";
            }

            \Log::info("Calcul total retenues", array_merge([
                'type_document'  => $this->estBonCommande() ? 'BC' : 'DA',
                'total_retenues' => $totalRetenues,
                'details'        => $detailsRetenues,
            ], array_filter([
                'montant_ir'       => $donnees['montant_ir']       ?? 0,
                'montant_cnps'     => $donnees['montant_cnps']     ?? 0,
                'montant_irnc'     => $donnees['montant_irnc']     ?? 0,
                'montant_tva'      => $donnees['montant_tva']      ?? 0,
                'montant_redevance' => $donnees['montant_redevance'] ?? 0,
                'montant_feicom'   => $donnees['montant_feicom']   ?? 0,
                'montant_tsr'      => $donnees['montant_tsr']      ?? 0,
                'autres_retenues'  => $donnees['autres_retenues']  ?? 0,
            ])));

            if ($totalRetenues > 0) {
                $tresorPublic = \App\Models\Fournisseur::firstOrCreate(
                    ['code' => 'TRESOR_PUBLIC'],
                    ['raison_sociale' => 'Trésor Public', 'type_fournisseur' => 'administration', 'actif' => true]
                );

                $opImpot = \App\Models\OrdonnancePaiement::create([
                    'numero'               => \App\Models\OrdonnancePaiement::genererNumero($this, 'impot'),
                    'numero_emission'      => \App\Models\OrdonnancePaiement::genererNumeroEmission(),
                    'type_ordonnance'      => 'impot',
                    'engagement_id'        => $this->id,
                    'ordonnance_parent_id' => $opStandard->id,
                    'exercice_id'          => $this->exercice_id,
                    'budget_id'            => $this->budget_id,
                    'beneficiaire_type'    => 'App\Models\Fournisseur',
                    'beneficiaire_id'      => $tresorPublic->id,
                    'date_emission'        => now(),
                    'montant_net'          => round($totalRetenues, 2),
                    'objet'                => $this->objet,
                    'reference_engagement' => $this->numero,
                    'statut'               => 'emise',
                    'created_by'           => auth()->id(),
                    'montant_ir'           => round($donnees['montant_ir']   ?? 0, 2),
                    'montant_irnc'         => round($donnees['montant_irnc'] ?? 0, 2),
                    'montant_cnps'         => round($donnees['montant_cnps'] ?? 0, 2),
                    'montant_tva'          => round($donnees['montant_tva']  ?? 0, 2),
                    'montant_tsr'          => round($donnees['montant_tsr']  ?? 0, 2),
                    'montant_autres_taxes' => round(
                        ($donnees['montant_redevance'] ?? 0)
                            + ($donnees['montant_feicom']   ?? 0)
                            + ($donnees['autres_retenues']  ?? 0),
                        2
                    ),
                ]);

                $ordonnances['impot'] = $opImpot;
                \Log::info("OP Impôt créée", ['numero' => $opImpot->numero, 'montant' => $opImpot->montant_net]);
            } else {
                \Log::info("Pas d'OP Impôt — aucune retenue", ['type_document' => $this->estBonCommande() ? 'BC' : 'DA']);
            }

            $totalOP = $opStandard->montant_net + ($ordonnances['impot']->montant_net ?? 0);
            \Log::info("✅ Ordonnances créées", [
                'engagement'  => $this->numero,
                'op_standard' => $opStandard->montant_net,
                'op_impot'    => $ordonnances['impot']->montant_net ?? 0,
                'total'       => $totalOP,
            ]);

            if (abs($donnees['montant_ttc'] - $totalOP) > 0.01) {
                \Log::warning("⚠️ Écart TTC vs total OP", [
                    'ttc'      => $donnees['montant_ttc'],
                    'total_op' => $totalOP,
                    'ecart'    => $donnees['montant_ttc'] - $totalOP,
                ]);
            }

            return $ordonnances;
        });
    }

    // =========================================================
    // EXTRACTION DONNÉES DOCUMENT SOURCE
    // =========================================================
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

        // CAS 1 : BON DE COMMANDE
        if ($this->estBonCommande() && $this->engageable) {
            $bc = $this->engageable;
            $montantHT  = $bc->montant_ht  ?? 0;
            $montantBrut = $bc->montant_ttc ?? 0;
            $montantTVA  = $bc->montant_tva ?? 0;
            $montantTSR  = $bc->montant_tsr ?? 0;
            $montantTTC  = $bc->montant_ttc ?? 0;
            $montantIR   = $bc->montant_ir  ?? 0;
            $montantNet  = $montantTTC - ($montantIR + $montantTVA + $montantTSR);
            $bc->load('fournisseur');
            $beneficiaire     = $bc->fournisseur;
            $beneficiaireType = 'App\Models\Fournisseur';
            \Log::info("BC - Montants extraits", ['bc_numero' => $bc->numero, 'montant_ttc' => $montantTTC, 'montant_net' => $montantNet]);
        }

        // CAS 2 : DÉCISION ADMINISTRATIVE
        elseif ($this->estDecision() && $this->engageable) {
            $da = $this->engageable;
            $montantBrut     = $da->montant_brut ?? 0;
            $montantTTC      = $montantBrut;
            $montantIR       = $da->montant_ir   ?? 0;
            $montantCNPS     = $da->montant_cnps  ?? 0;
            $montantIRNC     = $da->montant_irnc  ?? 0;
            $montantTVA      = $da->montant_tva_calcule ?? ($da->montant_tva ?? 0);
            $montantRedevance = $da->montant_redevance_audiovisuelle_calcule ?? ($da->montant_redevance_audiovisuelle ?? 0);
            $montantFeicom   = $da->montant_feicom_calcule ?? ($da->montant_feicom ?? 0);
            $autresRetenues  = $da->autres_retenues ?? 0;
            $montantNet      = $da->montant_net ?? 0;

            if (!$da->relationLoaded('personnel')) $da->load('personnel');

            if ($da->type_beneficiaire === 'fournisseur' && $da->fournisseur_id) {
                if (!$da->relationLoaded('fournisseur')) $da->load('fournisseur');
                $beneficiaire     = $da->fournisseur;
                $beneficiaireType = 'App\Models\Fournisseur';
            } elseif ($da->personnel_id) {
                if (!$da->relationLoaded('personnel')) $da->load('personnel');
                $beneficiaire     = $da->personnel;
                $beneficiaireType = 'App\Models\Personnel';
            } else {
                \Log::error("DA - Aucun bénéficiaire trouvé", ['da_id' => $da->id, 'type_beneficiaire' => $da->type_beneficiaire]);
            }

            \Log::info("DA - Montants extraits", [
                'da_numero'   => $da->numero ?? 'N/A',
                'montant_brut' => $montantBrut,
                'montant_net' => $montantNet,
            ]);
        }

        // CAS 3 : ENGAGEMENT MANUEL
        else {
            $montantTTC = $this->montant_engage;
            $montantIR  = $this->calculerMontantImpot();
            $montantNet = $montantTTC - $montantIR;

            if ($this->beneficiaire_type === 'App\Models\Fournisseur') {
                if (!$this->relationLoaded('beneficiaireFournisseur')) $this->load('beneficiaireFournisseur');
                $beneficiaire     = $this->beneficiaireFournisseur;
                $beneficiaireType = 'App\Models\Fournisseur';
            } elseif ($this->beneficiaire_type === 'App\Models\Personnel') {
                if (!$this->relationLoaded('beneficiairePersonnel')) $this->load('beneficiairePersonnel');
                $beneficiaire     = $this->beneficiairePersonnel;
                $beneficiaireType = 'App\Models\Personnel';
            }
        }

        \Log::info("Données extraites", [
            'engagement_numero' => $this->numero,
            'beneficiaire_existe' => $beneficiaire !== null,
            'beneficiaire_type' => $beneficiaireType,
            'beneficiaire_nom'  => $beneficiaire?->nom_complet ?? $beneficiaire?->raison_sociale ?? 'NULL',
        ]);

        return [
            'montant_ht'       => $montantHT,
            'montant_brut'     => $montantBrut,
            'montant_tva'      => $montantTVA,
            'montant_tsr'      => $montantTSR,
            'montant_ttc'      => $montantTTC,
            'montant_ir'       => $montantIR,
            'montant_cnps'     => $montantCNPS,
            'montant_irnc'     => $montantIRNC,
            'montant_redevance' => $montantRedevance,
            'montant_feicom'   => $montantFeicom,
            'autres_retenues'  => $autresRetenues,
            'montant_net'      => $montantNet,
            'beneficiaire'     => $beneficiaire,
            'beneficiaire_type' => $beneficiaireType,
        ];
    }

    protected function calculerMontantImpot(): float
    {
        if ($this->engageable_type && $this->engageable) return 0;

        $montant = $this->montant_engage;

        if (($this->beneficiaire_type === 'fournisseur' || $this->beneficiaire_type === 'App\Models\Fournisseur')
            && $this->beneficiaireFournisseur
        ) {
            $fournisseur = $this->beneficiaireFournisseur;
            if (!$fournisseur->relationLoaded('regimeFiscal')) $fournisseur->load('regimeFiscal');
            if ($fournisseur->regimeFiscal) {
                return round(($montant * ($fournisseur->regimeFiscal->taux_ir_defaut ?? 0)) / 100, 2);
            }
        }

        if ($this->beneficiaire_type === 'personnel' || $this->beneficiaire_type === 'App\Models\User') {
            if ($montant < 500000)  return round(($montant * 5.5)  / 100, 2);
            if ($montant < 3000000) return round(($montant * 11.0) / 100, 2);
            return round(($montant * 15.0) / 100, 2);
        }

        return 0;
    }
}
