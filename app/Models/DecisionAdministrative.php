<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Traits\HasExercice;
use App\Traits\HasWorkflow;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Exceptions\CreditBudgetaireInsuffisantException;
use App\Traits\GereTransmissions;
use App\Traits\HasRecentValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionAdministrative extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity, GereTransmissions, HasWorkflow;

    protected $table = 'decisions_administratives';

    protected $fillable = [
        'exercice_id',
        'numero',
        'budget_id',
        'type_beneficiaire',
        'personnel_id',
        'fournisseur_id',
        'nom_personnel',
        'matricule',
        'fonction',
        'type_decision_id',
        'date_decision',
        'date_effet',
        'date_fin',
        'objet',
        'montant_brut',
        'montant_ht',
        'taux_cnps',
        'taux_irnc',
        'montant_cnps',
        'montant_irnc',
        'type_tva',
        'taux_tva',
        'montant_tva',
        'type_redevance_audiovisuelle',
        'taux_redevance_audiovisuelle',
        'montant_redevance_audiovisuelle',
        'type_feicom',
        'taux_feicom',
        'montant_feicom',
        'autres_retenues',
        'total_taxes',
        'montant_net',
        'reference_decision',
        'signataire',
        'statut',
        'validee_par',
        'date_validation',
        'engagee',
        'montant_engage',
        'date_engagement',
        'observations',
        'created_by',
        'updated_by',
        'service_emetteur_id',
        'mode_saisie',
        'est_previsionnel',
        'da_reelle_id',
        'statut_avant_annulation',
        'taux_ir',
        'montant_ir',
        'type_irnc',
        'banque',
        'billetage',
    ];

    protected $casts = [
        'date_decision'                    => 'date',
        'date_effet'                       => 'date',
        'date_fin'                         => 'date',
        'date_validation'                  => 'datetime',
        'date_engagement'                  => 'datetime',
        'montant_brut'                     => 'decimal:2',
        'montant_ht'                       => 'decimal:2',
        'montant_cnps'                     => 'decimal:2',
        'montant_irnc'                     => 'decimal:2',
        'autres_retenues'                  => 'decimal:2',
        'total_taxes'                      => 'decimal:2',
        'montant_net'                      => 'decimal:2',
        'montant_engage'                   => 'decimal:2',
        'taux_cnps'                        => 'decimal:2',
        'taux_irnc'                        => 'decimal:2',
        'type_tva'                         => 'string',
        'taux_tva'                         => 'decimal:2',
        'montant_tva'                      => 'decimal:2',
        'type_redevance_audiovisuelle'     => 'string',
        'taux_redevance_audiovisuelle'     => 'decimal:2',
        'montant_redevance_audiovisuelle'  => 'decimal:2',
        'type_feicom'                      => 'string',
        'taux_feicom'                      => 'decimal:2',
        'montant_feicom'                   => 'decimal:2',
        'engagee'                          => 'boolean',
        'est_previsionnel'                 => 'boolean',
        'taux_ir'    => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'banque'     => 'decimal:2',
        'billetage'  => 'decimal:2',
    ];

    // =========================================================
    // BOOT
    // =========================================================
    protected static function booted(): void
    {
        static::creating(function ($decision) {
            if (!$decision->numero) {
                $exerciceId     = $decision->attributes['exercice_id']
                    ?? $decision->exercice_id
                    ?? null;

                // ✅ Passer aussi le type_decision_id pour le bon préfixe
                $typeDecisionId = $decision->attributes['type_decision_id']
                    ?? $decision->type_decision_id
                    ?? null;

                $decision->numero = static::genererNumero($exerciceId, $typeDecisionId);
            }
            if (!$decision->created_by) {
                $decision->created_by = auth()->id();
            }
        });

        static::saving(function ($decision) {
            $mode = $decision->attributes['mode_saisie']
                ?? $decision->getOriginal('mode_saisie')
                ?? $decision->mode_saisie
                ?? 'calcule';

            if ($mode === 'forfait') return;

            // ✅ Calculer uniquement si montant_brut est présent
            if ((float) ($decision->montant_brut ?? 0) > 0) {
                $decision->calculerMontants();
            }
        });

        static::updating(function ($decision) {
            $decision->updated_by = auth()->id();

            $champsAutorisesSansRestriction = [
                'engagement_id',
                'engagee',
                'montant_engage',
                'date_engagement',
                'statut',
                'statut_avant_annulation',
                'validee_par',
                'date_validation',
                'observations',
                'updated_by',
                'updated_at',
                'mode_saisie',
                'da_reelle_id',
            ];

            $champsDirty         = array_keys($decision->getDirty());
            $modificationAutorisee = empty(array_diff($champsDirty, $champsAutorisesSansRestriction));

            if ($modificationAutorisee) return;

            if (
                $decision->isDirty() &&
                $decision->getOriginal('statut') !== 'brouillon' &&
                !auth()->user()?->hasRole('super_admin')
            ) {
                throw new \Exception('Modification interdite : décision non brouillon.');
            }

            if (
                $decision->estEnCoursDeTransmission() &&
                !auth()->user()?->can('force_update_decision_administrative')
            ) {
                throw new \Exception('Modification interdite : décision en cours de transmission.');
            }
        });

        static::deleting(function ($decision) {
            if (!auth()->user()?->hasRole('super_admin')) {
                throw new \Exception('Suppression interdite : réservé au super administrateur.');
            }
            if ($decision->engagee) {
                throw new \Exception("Suppression interdite : décision déjà engagée. Annulez-la d'abord.");
            }
        });
    }

    // =========================================================
    // RELATIONS
    // =========================================================
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function typeDecision()
    {
        return $this->belongsTo(TypeDecision::class, 'type_decision_id');
    }

    public function engagements()
    {
        return $this->morphMany(\App\Models\Engagement::class, 'engageable');
    }

    public function ligneBudgetaire()
    {
        return $this->belongsTo(LigneBudgetaire::class, 'budgetaire_ligne_id');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function serviceEmetteur(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_emetteur_id');
    }

    public function validateurUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    public function engagement(): MorphOne
    {
        return $this->morphOne(Engagement::class, 'engageable');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    // public function transmissions()
    // {
    //     return $this->morphMany(Transmission::class, 'document');
    // }

    public function daReelle(): BelongsTo
    {
        return $this->belongsTo(DecisionAdministrative::class, 'da_reelle_id');
    }

    public function decisionsPrevisionnelles(): HasMany
    {
        return $this->hasMany(DecisionAdministrative::class, 'da_reelle_id');
    }

    // Dans app/Models/DecisionAdministrative.php — ajouter dans la section RELATIONS

    public function regiesAvances(): HasMany
    {
        return $this->hasMany(\App\Models\RegieAvance::class, 'decision_administrative_id');
    }

    // =========================================================
    // SCOPES
    // =========================================================
    public function scopeType($query, $type)
    {
        return $query->where('type_decision', $type);
    }

    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeEngagees($query)
    {
        return $query->where('engagee', true);
    }

    // =========================================================
    // ACCESSEURS
    // =========================================================
    public function getMontantCnpsCalculeAttribute(): float
    {
        $ht   = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_cnps ?? 0);
        return $ht * ($taux / 100);
    }

    public function getMontantIrncCalculeAttribute(): float
    {
        $ht   = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_irnc ?? 0);
        return $ht * ($taux / 100);
    }

    public function getMontantTvaCalculeAttribute(): float
    {
        if ($this->type_tva === 'forfait') {
            return (float) ($this->attributes['montant_tva'] ?? 0);
        }
        $brut = (float) ($this->montant_brut ?? 0);
        $ht   = (float) ($this->montant_ht ?? 0);
        return $brut - $ht;
    }

    public function getMontantRedevanceAudiovisuelleCalculeAttribute(): float
    {
        if ($this->type_redevance_audiovisuelle === 'forfait') {
            return (float) ($this->attributes['montant_redevance_audiovisuelle'] ?? 0);
        }
        $ht   = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_redevance_audiovisuelle ?? 0);
        return $ht * ($taux / 100);
    }

    public function getMontantFeicomCalculeAttribute(): float
    {
        if ($this->type_feicom === 'forfait') {
            return (float) ($this->attributes['montant_feicom'] ?? 0);
        }
        $ht   = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_feicom ?? 0);
        return $ht * ($taux / 100);
    }

    public function getTotalRetenuesCalculeAttribute(): float
    {
        return $this->montant_cnps_calcule
            + $this->montant_ir_calcule
            + $this->montant_irnc_calcule
            + $this->montant_tva_calcule
            + $this->montant_redevance_audiovisuelle_calcule
            + $this->montant_feicom_calcule
            + ((float) ($this->autres_retenues ?? 0));
    }

    public function getMontantNetCalculeAttribute(): float
    {
        return (float) ($this->montant_brut ?? 0) - $this->total_retenues_calcule;
    }

    public function getEstPrevisionnelAttribute(): bool
    {
        return (bool) ($this->attributes['est_previsionnel'] ?? false);
    }

    public function getEstConvertiAttribute(): bool
    {
        return $this->est_previsionnel && !is_null($this->da_reelle_id);
    }

    public function getMontantIrCalculeAttribute(): float
    {
        $ht   = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_ir    ?? 0);
        return $ht * ($taux / 100);
    }
    
    // =========================================================
    // MÉTHODES MÉTIER
    // =========================================================

    /**
     * Générer le numéro de décision.
     * ✅ Accepte un exercice_id explicite pour les reports (2025 → DA25-XXXXX)
     * Format: DA25-00001 / DA26-00001
     */
    public static function genererNumero(
        ?int $exerciceId    = null,
        ?int $typeDecisionId = null
    ): string {
        $exercice = $exerciceId
            ? \App\Models\Exercice::find($exerciceId)
            : \App\Models\Exercice::getActif();

        if (!$exercice) {
            throw new \Exception("Aucun exercice disponible pour générer le numéro");
        }

        $annee  = substr($exercice->annee, -2);

        // ✅ Déterminer le préfixe selon le type de décision
        $prefix = static::getPrefixParType($typeDecisionId);

        return \DB::transaction(function () use ($annee, $exercice, $prefix) {
            $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM decisions_administratives
            WHERE exercice_id  = :exercice_id
            AND   numero LIKE  :pattern
        ", [
                'exercice_id' => $exercice->id,
                'pattern'     => "{$prefix}{$annee}-%",
            ]);

            $sequence = ($result->max_seq ?? 0) + 1;
            return sprintf('%s%s-%05d', $prefix, $annee, $sequence);
        });
    }

    /**
     * ✅ Préfixe selon le type de décision
     */
    public static function getPrefixParType(?int $typeDecisionId): string
    {
        if (!$typeDecisionId) return 'DA';

        $type = \App\Models\TypeDecision::find($typeDecisionId);
        if (!$type) return 'DA';

        $libelle = strtolower(trim($type->libelle ?? ''));

        return match (true) {
            // ✅ Correspondances exactes depuis votre liste
            str_contains($libelle, 'ordre de mission')       => 'OM',
            str_contains($libelle, 'arrêté')                 => 'AR',
            str_contains($libelle, 'arrete')                 => 'AR',
            str_contains($libelle, 'note de service')        => 'NS',
            str_contains($libelle, 'circulaire')             => 'CI',
            str_contains($libelle, 'contrat')                => 'CT',
            str_contains($libelle, 'convention')             => 'CP',

            default => 'DA',
        };
    }

    /**
     * Générer le numéro d'engagement lié à cette DA.
     * ✅ Utilise l'exercice_id du document pour cohérence BE-DA25-XXXXX
     */
    protected function genererNumeroEngagement(): string
    {
        if (!$this->numero) {
            $this->numero = static::genererNumero(
                $this->exercice_id,
                $this->type_decision_id
            );
        }

        // ✅ BE-DA26-00001 ou BE-OM26-00001
        return 'BE-' . $this->numero;
    }

    /**
     * Calculer tous les montants (CNPS, IRNC, TVA, Redevance, FEICOM, net)
     */
    public function calculerMontants(): void
    {
        $mode = $this->attributes['mode_saisie']
            ?? $this->getOriginal('mode_saisie')
            ?? $this->mode_saisie
            ?? 'calcule';

        if ($mode === 'forfait') {
            \Log::info('calculerMontants() — ignoré (forfait)', ['da' => $this->numero]);
            return;
        }

        $brut = (float) ($this->montant_brut ?? 0);

        if ($brut <= 0) {
            $this->montant_ht   = 0;
            $this->montant_cnps = 0;
            $this->montant_ir   = 0;   // ✅
            $this->montant_irnc = 0;
            $this->total_taxes  = 0;
            $this->montant_net  = 0;
            return;
        }

        // ── HT ────────────────────────────────────────────────────
        $tauxTva = 0;
        if ($this->type_tva === 'taux') {
            $tauxTva = (float) ($this->taux_tva ?? 0);
        }

        $montantHT        = $tauxTva > 0 ? $brut / (1 + $tauxTva / 100) : $brut;
        $this->montant_ht = round($montantHT, 2);

        // ── Taxes ─────────────────────────────────────────────────
        $tauxCnps         = (float) ($this->taux_cnps ?? 0);
        $this->montant_cnps = round($montantHT * ($tauxCnps / 100), 2);

        // ✅ IR standard
        $tauxIr           = (float) ($this->taux_ir ?? 0);
        $this->montant_ir = round($montantHT * ($tauxIr / 100), 2);

        // ✅ IRNC (taux ou forfait)
        if (($this->type_irnc ?? 'taux') === 'forfait') {
            // Montant IRNC déjà saisi — ne pas recalculer
            $this->montant_irnc = round((float) ($this->attributes['montant_irnc'] ?? 0), 2);
        } else {
            $tauxIrnc           = (float) ($this->taux_irnc ?? 0);
            $this->montant_irnc = round($montantHT * ($tauxIrnc / 100), 2);
        }

        // TVA
        if ($this->type_tva === 'taux') {
            $this->attributes['montant_tva'] = round($montantHT * ($tauxTva / 100), 2);
        }

        // Redevance
        if ($this->type_redevance_audiovisuelle === 'taux') {
            $tauxRed = (float) ($this->taux_redevance_audiovisuelle ?? 0);
            $this->attributes['montant_redevance_audiovisuelle'] = round($montantHT * ($tauxRed / 100), 2);
        }

        // FEICOM
        if ($this->type_feicom === 'taux') {
            $tauxFeicom = (float) ($this->taux_feicom ?? 0);
            $this->attributes['montant_feicom'] = round($montantHT * ($tauxFeicom / 100), 2);
        }

        // ── Total et net ───────────────────────────────────────────
        $autresRetenues    = (float) ($this->autres_retenues ?? 0);
        $this->total_taxes = $this->montant_cnps
            + $this->montant_ir
            + $this->montant_irnc
            + ((float) ($this->attributes['montant_redevance_audiovisuelle'] ?? 0))
            + ((float) ($this->attributes['montant_feicom'] ?? 0))
            + $autresRetenues;

        $this->montant_net = round($montantHT - $this->total_taxes, 2);
    }

    // ── Valider ───────────────────────────────────────────────
    public function valider(User $user): void
    {
        $this->verifierPasEnTransmission('valider');
        if (!auth()->check() || !auth()->user()->can('valider_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission de valider cette décision.");
        }

        // ✅ updateQuietly → bypass saving → calculerMontants() jamais appelé
        $this->updateQuietly([
            'statut'           => 'validee',
            'validee_par'      => $user->id,
            'date_validation'  => now(),
        ]);
    }

    // ── peutEtreDesengagee ────────────────────────────────────
    public function peutEtreDesengagee(): bool
    {
        if (!$this->engagee) return false;
        if ($this->engagement?->ordonnancesPaiement()->count() > 0) return false;
        if ($this->statut === 'annulee') return false;
        return true;
    }

    // ── engagerBudget ─────────────────────────────────────────
    public function engagerBudget(int $nomenclatureId): Engagement
    {
        $this->verifierPasEnTransmission('engager');
        if (!auth()->check() || !auth()->user()->can('engager_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission d'engager cette décision.");
        }
        if ($this->statut !== 'validee') {
            throw new \Exception("La décision doit être validée avant d'engager le budget.");
        }
        if ($this->engagee) {
            throw new \Exception("Le budget est déjà engagé pour cette décision.");
        }

        \DB::beginTransaction();
        try {
            $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                ->where('nomenclature_id', $nomenclatureId)
                ->firstOrFail();

            if (!$this->est_previsionnel) {
                if (!$ligneBudgetaire->peutEngager($this->montant_brut)) {
                    $nomenclature = $ligneBudgetaire->nomenclature;
                    $manque       = $this->montant_brut - $ligneBudgetaire->disponible_engagement;
                    throw new \App\Exceptions\CreditBudgetaireInsuffisantException(
                        "❌ CRÉDIT INSUFFISANT\n\n" .
                            "Ligne budgétaire: {$nomenclature->code} - {$nomenclature->libelle}\n\n" .
                            "• Provision totale: " . number_format($ligneBudgetaire->montant_vote, 0, ',', ' ') . " FCFA\n" .
                            "• Déjà engagé: "      . number_format($ligneBudgetaire->engage, 0, ',', ' ')       . " FCFA\n" .
                            "• Disponible: "        . number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . " FCFA\n\n" .
                            "• Montant demandé: "   . number_format($this->montant_brut, 0, ',', ' ') . " FCFA\n" .
                            "• Manque: "            . number_format($manque, 0, ',', ' ') . " FCFA"
                    );
                }
            }

            $numeroEngagement = $this->genererNumeroEngagement();

            $engagement = Engagement::create([
                'numero'                     => $numeroEngagement,
                'exercice_id'                => $this->exercice_id,
                'budget_id'                  => $this->budget_id,
                'type_engagement'            => 'Décision',
                'nomenclature_principale_id' => $nomenclatureId,
                'reference_document'         => $this->numero,
                'engageable_type'            => get_class($this),
                'engageable_id'              => $this->id,
                'beneficiaire_type'          => $this->type_beneficiaire === 'fournisseur'
                    ? 'App\Models\Fournisseur'
                    : 'App\Models\Personnel',
                'beneficiaire_id'            => $this->type_beneficiaire === 'fournisseur'
                    ? $this->fournisseur_id
                    : $this->personnel_id,
                'date_engagement'            => now(),
                'exercice'    => \App\Models\Exercice::getActif()?->annee ?? now()->year,
                'objet'                      => $this->objet,
                'montant_engage'             => $this->montant_brut,
                'statut'                     => 'provisoire',
                'type'                       => $this->est_previsionnel ? 'previsionnel' : 'standard',
                'created_by'                 => auth()->id(),
            ]);

            if (!$engagement?->id) {
                throw new \Exception("Erreur lors de la création de l'engagement.");
            }

            $engagement->refresh();

            $ligneEngagement = LigneEngagement::create([
                'engagement_id'  => $engagement->id,
                'nomenclature_id' => $nomenclatureId,
                'numero_ligne'   => 1,
                'libelle'        => $this->objet,
                'montant'        => $this->montant_brut,
            ]);

            if (!$ligneEngagement?->id) {
                throw new \Exception("Erreur lors de la création de la ligne d'engagement.");
            }

            if (!$this->est_previsionnel) {
                $ligneBudgetaire->enregistrerEngagement($this->montant_brut);
            }

            $this->updateQuietly([
                'engagee'         => true,
                'montant_engage'  => $this->montant_brut,
                'date_engagement' => now(),
                'statut'          => 'engagee',
            ]);

            \DB::commit();

            \Log::info("Engagement créé depuis DA" . ($this->est_previsionnel ? ' [PRÉVISIONNEL]' : ''), [
                'da_numero'         => $this->numero,
                'engagement_numero' => $engagement->numero,
                'montant'           => $this->montant_brut,
            ]);

            return $engagement;
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Erreur engagement DA", ['da_id' => $this->id, 'erreur' => $e->getMessage()]);
            throw $e;
        }
    }

    // ── desengagerBudget ──────────────────────────────────────
    public function desengagerBudget(): void
    {
        if (!auth()->check() || !auth()->user()->can('annuler_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission de désengager cette décision.");
        }
        if (!$this->engagee) {
            throw new \Exception("Cette décision n'est pas engagée.");
        }

        $engagement = \App\Models\Engagement::where('engageable_id', $this->id)
            ->where(function ($q) {
                $q->where('engageable_type', static::class)
                    ->orWhere('engageable_type', 'decision_administrative');
            })->first();

        if (!$engagement) {
            $this->updateQuietly([
                'engagee'         => false,
                'montant_engage'  => 0,
                'date_engagement' => null,
                'statut'          => 'validee',
            ]);
            return;
        }

        $engagement->load('lignes');
        $engagement->annuler(force: true);

        $this->updateQuietly([
            'engagee'         => false,
            'montant_engage'  => 0,
            'date_engagement' => null,
            'statut'          => 'validee',
        ]);

        \Log::info("DA {$this->numero} désengagée — engagement supprimé définitivement");
    }

    // ── annuler ───────────────────────────────────────────────
    public function annuler(?string $motif = null): void
    {
        $this->verifierPasEnTransmission();

        if (!auth()->check() || !auth()->user()->can('annuler_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission d'annuler cette décision.");
        }
        if ($this->statut === 'annulee') {
            throw new \Exception("Cette décision est déjà annulée.");
        }
        if ($this->engagee) {
            throw new \Exception(
                "❌ Annulation impossible : cette décision est déjà engagée.\n\n" .
                    "Veuillez d'abord annuler l'engagement N° " . ($this->engagement?->numero ?? '') .
                    " depuis la fiche de l'engagement, puis revenez annuler la décision."
            );
        }

        \DB::beginTransaction();
        try {
            $statutAvant = $this->statut;
            $this->updateQuietly([
                'statut'                  => 'annulee',
                'statut_avant_annulation' => $statutAvant,
                'observations'            => ($this->observations ?? '') .
                    "\n\n--- ANNULÉE LE " . now()->format('d/m/Y H:i') . " ---\n" .
                    "Statut avant : {$statutAvant}\n" .
                    "Motif : " . ($motif ?? 'Non précisé') . "\n" .
                    "Par : " . auth()->user()->name,
            ]);
            \DB::commit();
            \Log::info("DA {$this->numero} annulée", ['statut_avant' => $statutAvant]);
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    // ── peutEtreAnnulee ───────────────────────────────────────
    public function peutEtreAnnulee(): bool
    {
        if ($this->statut === 'annulee') return false;
        if ($this->engagee && $this->engagement) {
            return $this->engagement->ordonnancesPaiement()->count() === 0;
        }
        return true;
    }

    // ── peutEtreRecuperee ─────────────────────────────────────
    public function peutEtreRecuperee(): bool
    {
        return $this->statut === 'annulee';
    }

    // ── recuperer ─────────────────────────────────────────────
    public function recuperer(?string $motif = null): void
    {
        if (!auth()->check() || !auth()->user()->can('recuperer_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission de récupérer cette décision.");
        }
        if ($this->statut !== 'annulee') {
            throw new \Exception("Seule une décision annulée peut être récupérée.");
        }

        \DB::beginTransaction();
        try {
            $this->updateQuietly([
                'statut'                  => 'brouillon',
                'statut_avant_annulation' => null,
                'engagee'                 => false,
                'montant_engage'          => 0,
                'date_engagement'         => null,
                'validee_par'             => null,
                'date_validation'         => null,
                'observations'            => ($this->observations ?? '') .
                    "\n\n--- RÉCUPÉRÉE LE " . now()->format('d/m/Y H:i') . " ---\n" .
                    "Motif : " . ($motif ?? 'Document récupéré pour modification') . "\n" .
                    "Par : " . auth()->user()->name,
            ]);
            \DB::commit();
            \Log::info("DA {$this->numero} récupérée — remise en brouillon");
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    // ── estModifiable ─────────────────────────────────────────
    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'validee']);
    }

    // ── getNomCompletPersonnel ────────────────────────────────
    public function getNomCompletPersonnel(): string
    {
        if ($this->type_beneficiaire === 'fournisseur' && $this->fournisseur) {
            return $this->fournisseur->raison_sociale;
        }
        if ($this->personnel && !empty($this->personnel->nom_complet)) {
            return $this->personnel->nom_complet;
        }
        return '';
    }

    // ── convertirEnDAReelle ───────────────────────────────────
    public function convertirEnDAReelle(): DecisionAdministrative
    {
        if (!$this->est_previsionnel) {
            throw new \Exception("Cette décision n'est pas prévisionnelle.");
        }
        if ($this->est_converti) {
            throw new \Exception("Cette décision prévisionnelle a déjà été convertie.");
        }

        return \DB::transaction(function () {
            $daReelle = static::create([
                'exercice_id'       => $this->exercice_id,
                'budget_id'         => $this->budget_id,
                'type_decision_id'  => $this->type_decision_id,
                'type_beneficiaire' => $this->type_beneficiaire,
                'personnel_id'      => $this->personnel_id,
                'fournisseur_id'    => $this->fournisseur_id,
                'date_decision'     => now()->toDateString(),
                'date_effet'        => $this->date_effet,
                'date_fin'          => $this->date_fin,
                'objet'             => $this->objet,
                'mode_saisie'       => $this->mode_saisie,
                'est_previsionnel'  => false,
                'montant_brut'      => $this->montant_brut,
                'montant_ht'        => $this->montant_ht,
                'montant_tva'       => $this->montant_tva,
                'montant_cnps'      => $this->montant_cnps,
                'montant_irnc'      => $this->montant_irnc,
                'total_taxes'       => $this->total_taxes,
                'montant_net'       => $this->montant_net,
                'taux_tva'          => $this->taux_tva,
                'taux_cnps'         => $this->taux_cnps,
                'taux_irnc'         => $this->taux_irnc,
                'type_tva'          => $this->type_tva,
                'autres_retenues'   => $this->autres_retenues,
                'signataire'        => $this->signataire,
                'observations'      => "Convertie depuis DA Prévisionnelle N° {$this->numero}",
                'statut'            => 'brouillon',
                'created_by'        => auth()->id(),
            ]);

            $this->updateQuietly(['da_reelle_id' => $daReelle->id]);

            return $daReelle;
        });
    }

    // =========================================================
    // TRANSMISSIONS
    // =========================================================
    // public function estEnCoursDeTransmission(): bool
    // {
    //     return $this->transmissions()->where('statut', 'en_attente')->exists();
    // }

    // public function estDestinataireActuel(): bool
    // {
    //     $t = $this->transmissions()->where('statut', 'en_attente')->latest()->first();
    //     return $t && $t->destinataire_id === auth()->id();
    // }

    public function peutEtreVuPar(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        if (auth()->user()?->hasRole('super_admin')) return true;
        if (!$this->estEnCoursDeTransmission()) return true;
        return $this->estDestinataireActuel();
    }

    // public function transmettreA(
    //     User $destinataire,
    //     string $actionAttendue,
    //     ?string $commentaire = null,
    //     array $metadata = []
    // ): Transmission {
    //     if ($this->estEnCoursDeTransmission()) {
    //         throw new \Exception('Cette décision est déjà en cours de transmission.');
    //     }
    //     $transmission = new Transmission([
    //         'document_type'      => static::class,
    //         'document_id'        => $this->id,
    //         'expediteur_id'      => auth()->id(),
    //         'destinataire_id'    => $destinataire->id,
    //         'action_attendue'    => $actionAttendue,
    //         'commentaire'        => $commentaire,
    //         'statut'             => 'en_attente',
    //         'priorite'           => $metadata['priorite'] ?? 'normale',
    //         'date_limite'        => $metadata['date_limite'] ?? null,
    //         'date_transmission'  => now(),
    //     ]);
    //     $transmission->save();
    //     activity()->performedOn($this)->causedBy(auth()->user())
    //         ->withProperties(['destinataire' => $destinataire->name])
    //         ->log('Décision transmise');
    //     return $transmission;
    // }

    // public function cloturerTransmission(?string $reponse = null): void
    // {
    //     $t = $this->transmissions()->where('statut', 'en_attente')->latest()->first();
    //     if (!$t || $t->destinataire_id !== auth()->id()) {
    //         throw new \Exception("Vous n'êtes pas le destinataire de cette transmission.");
    //     }
    //     $t->update(['statut' => 'traite', 'date_traitement' => now(), 'reponse' => $reponse]);
    //     activity()->performedOn($this)->causedBy(auth()->user())->log('Transmission clôturée');
    // }

    public function peutEtreTransmis(): bool
    {
        if ($this->estEnCoursDeTransmission()) return false;
        return in_array($this->statut, ['brouillon', 'valide']);
    }

    // public function transmissionEnCours(): ?Transmission
    // {
    //     return $this->transmissions()->where('statut', 'en_attente')->latest()->first();
    // }

    public function aEteTransmis(): bool
    {
        return $this->transmissions()->exists();
    }

    public function historiqueTransmissions()
    {
        return $this->transmissions()->with(['expediteur', 'destinataire'])
            ->orderBy('created_at', 'desc')->get();
    }

    // protected function estEnTransmission(): bool
    // {
    //     // ✅ Via la méthode du trait HasWorkflow — évite les problèmes de morphMap
    //     return $this->record->estEnCoursDeTransmission();
    // }

    // protected function estDestinataire(): bool
    // {
    //     // ✅ Via la méthode du trait HasWorkflow
    //     return $this->record->estDestinataireActuel();
    // }

    // protected function transmissionEnCours(): ?Transmission
    // {
    //     // ✅ Via la relation morphMany du trait
    //     return $this->record->transmissionEnCours();
    // }

    // =========================================================
    // ACTIVITY LOG
    // =========================================================
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'montant_brut', 'montant_net'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Décision administrative {$eventName}");
    }
}
