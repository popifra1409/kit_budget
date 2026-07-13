<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasExercice;
use App\Traits\GereTransmissions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrdonnancePaiement extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, HasExercice, GereTransmissions;

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
        'montant_tva',
        'montant_ir',
        'montant_tsr',
        'montant_cnps',
        'montant_irnc',
        'montant_autres_taxes',
        'date_emission',
        'mois_emission',
        'numero_bon',
        'numero_emission',
        'numero_op',
        'periode',
        'statut',
        'date_paiement',
        'reference_paiement',
        'mode_paiement',
        'observations',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'date_emission'        => 'date',
        'date_paiement'        => 'date',
        'montant_brut'         => 'decimal:2',
        'montant_impot'        => 'decimal:2',
        'montant_net'          => 'decimal:2',
        'montant_pec'          => 'decimal:2',
        'montant_tva'          => 'decimal:2',
        'montant_ir'           => 'decimal:2',
        'montant_tsr'          => 'decimal:2',
        'montant_cnps'         => 'decimal:2',
        'montant_irnc'         => 'decimal:2',
        'montant_autres_taxes' => 'decimal:2',
        'metadata'             => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($op) {
            if (!$op->numero) {
                $op->numero = $op->genererNumero();
            }
            if (!$op->created_by) {
                $op->created_by = auth()->id();
            }
        });
    }

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
    | ACCESSEURS
    |--------------------------------------------------------------------------
    */

    public function getBonCommandeAttribute()
    {
        if (!$this->engagement) return null;

        if (!$this->engagement->relationLoaded('engageable')) {
            $this->engagement->load('engageable');
        }

        if ($this->engagement->engageable_type === BonCommande::class) {
            return $this->engagement->engageable;
        }

        return null;
    }

    public function calculerMontantTotalImpots(): float
    {
        if ($this->montant_impot > 0) return (float) $this->montant_impot;

        $total = ($this->montant_tva         ?? 0)
            + ($this->montant_ir          ?? 0)
            + ($this->montant_tsr         ?? 0)
            + ($this->montant_cnps        ?? 0)
            + ($this->montant_irnc        ?? 0)
            + ($this->montant_autres_taxes ?? 0);

        if ($total == 0) {
            $bonCommande = $this->bonCommande;
            if ($bonCommande && method_exists($bonCommande, 'calculerMontantTotalImpots')) {
                return $bonCommande->calculerMontantTotalImpots();
            }
        }

        return $total;
    }

    public function getDetailImpots(): array
    {
        if (!$this->relationLoaded('engagement')) {
            $this->load('engagement.engageable');
        }

        $engagement = $this->engagement;

        if (!$engagement) {
            return ['ir' => 0, 'tva' => 0, 'tsr' => 0, 'cnps' => 0, 'irnc' => 0, 'redevance_av' => 0, 'feicom' => 0, 'autres' => 0, 'total' => 0];
        }

        $donnees = $engagement->extraireDonneesDocument();

        $ir        = (float) ($donnees['montant_ir']       ?? 0);
        $tva       = (float) ($donnees['montant_tva']      ?? 0);
        $tsr       = (float) ($donnees['montant_tsr']      ?? 0);
        $cnps      = (float) ($donnees['montant_cnps']     ?? 0);
        $irnc      = (float) ($donnees['montant_irnc']     ?? 0);
        $redevance = (float) ($donnees['montant_redevance'] ?? $donnees['montant_redevance_audiovisuelle'] ?? 0);
        $feicom    = (float) ($donnees['montant_feicom']   ?? 0);
        $autres    = (float) ($donnees['autres_retenues']  ?? 0);

        if ($engagement->estBonCommande()) {
            $total = $ir + $tva + $tsr;
            return ['ir' => $ir, 'tva' => $tva, 'tsr' => $tsr, 'cnps' => $cnps, 'irnc' => 0, 'redevance_av' => 0, 'feicom' => 0, 'autres' => 0, 'total' => $total];
        }

        $irTotal = $ir + $irnc;
        $total   = $irTotal + $cnps + $tva + $redevance + $feicom + $autres;

        return ['ir' => $irTotal, 'tva' => $tva, 'tsr' => 0, 'cnps' => $cnps, 'irnc' => 0, 'redevance_av' => $redevance, 'feicom' => $feicom, 'autres' => $autres, 'total' => $total];
    }

    public function hasImpots(): bool
    {
        return $this->calculerMontantTotalImpots() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | NUMÉROTATION
    |--------------------------------------------------------------------------
    */

    /**
     * ✅ CORRIGÉ — supprime lockForUpdate() + COUNT/FIRST sur agrégat
     *
     * PostgreSQL interdit FOR UPDATE avec COUNT(*) ou avec des agrégats.
     * Remplacement par advisory lock + MAX() via raw SQL.
     *
     * Deux cas :
     *   ① Pas de document source → séquence annuelle  (OP-26-00001)
     *   ② Document source trouvé → basé sur son numéro (OP-DA26-00206)
     *      Si collision (OP supprimée puis recréée) → suffixe -2, -3...
     */
    public static function genererNumero($engagement, string $type = 'standard'): string
    {
        $prefixe = $type === 'impot' ? 'OPT-' : 'OP-';

        if (is_numeric($engagement)) {
            $engagement = \App\Models\Engagement::with('engageable')->find($engagement);
        }

        if (!$engagement) {
            throw new \Exception("L'engagement est requis.");
        }

        if (!$engagement->relationLoaded('engageable')) {
            $engagement->load('engageable');
        }

        $numeroDocumentSource = $engagement->engageable?->numero
            ?? $engagement->reference_document;

        // ── ① Fallback séquentiel (pas de document source) ──────────────
        if (!$numeroDocumentSource) {
            return DB::transaction(function () use ($prefixe) {
                $annee    = now()->format('y');
                $pattern  = $prefixe . $annee . '-%';
                $lockKey  = crc32('op_seq_' . $prefixe . $annee);

                // Advisory lock → thread-safe sans FOR UPDATE
                DB::statement("SELECT pg_advisory_xact_lock({$lockKey})");

                // ✅ MAX() au lieu de ->first() + lockForUpdate()
                $result = DB::selectOne(
                    "SELECT COALESCE(
                        MAX(CAST(SPLIT_PART(numero, '-', 3) AS INTEGER)),
                        0
                    ) AS max_seq
                    FROM ordonnances_paiement
                    WHERE numero LIKE :pattern
                      AND deleted_at IS NULL",
                    ['pattern' => $pattern]
                );

                $sequence = ($result->max_seq ?? 0) + 1;
                return $prefixe . $annee . '-' . sprintf('%05d', $sequence);
            });
        }

        // ── ② Format basé sur le numéro du document source ──────────────
        // Ex : OP-DA26-00206 ou OPT-DA26-00206
        $base = $prefixe . $numeroDocumentSource;

        // Vérifier si ce numéro exact existe déjà (soft-deleted inclus)
        // Cas typique : OP supprimée puis recréée pour le même engagement
        $existe = static::withTrashed()->where('numero', $base)->exists();

        if (!$existe) {
            return $base;
        }

        // ── ③ Collision → suffixe séquentiel -2, -3... ──────────────────
        // Ex : OP-DA26-00206 existe → crée OP-DA26-00206-2
        return DB::transaction(function () use ($base) {
            $lockKey = crc32('op_collision_' . $base);

            // Advisory lock → thread-safe sans FOR UPDATE
            DB::statement("SELECT pg_advisory_xact_lock({$lockKey})");

            // ✅ MAX() sur le suffixe numérique — remplace lockForUpdate()->count()
            //    Avant : ->lockForUpdate()->count() → FOR UPDATE + COUNT = erreur PostgreSQL
            //    Après : raw SQL MAX() sans verrou de ligne
            $result = DB::selectOne(
                "SELECT COALESCE(
                    MAX(
                        CASE
                            WHEN numero ~ (:base_escaped || '[-][0-9]+$')
                            THEN CAST(REGEXP_REPLACE(numero, '^.*-([0-9]+)$', '\\1') AS INTEGER)
                            ELSE 1
                        END
                    ), 1
                ) AS max_suffixe
                FROM ordonnances_paiement
                WHERE numero LIKE :pattern",
                [
                    'base_escaped' => preg_quote($base, '/'),
                    'pattern'      => $base . '%',
                ]
            );

            $prochain = ($result->max_suffixe ?? 1) + 1;
            return $base . '-' . $prochain;
        });
    }

    public static function genererNumeroEmission(): string
    {
        $annee   = now()->format('y');
        $pattern = "EM{$annee}-%";

        $dernier = static::where('numero_emission', 'like', $pattern)
            ->orderBy('numero_emission', 'desc')
            ->value('numero_emission');

        $sequence = $dernier ? ((int) substr($dernier, -5)) + 1 : 1;

        return sprintf('EM%s-%05d', $annee, $sequence);
    }

    /**
     * Générer un numéro d'OP basé sur le numéro du BON DE COMMANDE
     * BC-2025-001 → OP-2025-001 (standard) ou OPT-2025-001 (impôt)
     */
    public static function genererNumeroFromBonCommande(BonCommande $bonCommande, string $type = 'standard'): string
    {
        $parts = explode('-', $bonCommande->numero);

        if (count($parts) >= 3) {
            $annee  = $parts[1];
            $numero = $parts[2];
            $prefix = $type === 'impot' ? 'OPT' : 'OP';
            return "{$prefix}-{$annee}-{$numero}";
        }

        return static::genererNumero($type);
    }

    public static function creerDepuisBonCommande(BonCommande $bonCommande): self
    {
        return DB::transaction(function () use ($bonCommande) {
            $type     = $bonCommande->type_depense === 'impot' ? 'impot' : 'standard';
            $numeroOP = static::genererNumero($type);

            return static::create([
                'numero'          => $numeroOP,
                'numero_op'       => $numeroOP,
                'numero_emission' => static::genererNumeroEmission(),
                'bon_commande_id' => $bonCommande->id,
                'budget_id'       => $bonCommande->budget_id,
                'fournisseur_id'  => $bonCommande->fournisseur_id,
                'montant_brut'    => $bonCommande->montant_total,
                'montant_net'     => $bonCommande->montant_net,
                'type_ordonnance' => $type,
                'statut'          => 'brouillon',
                'date_emission'   => now(),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW
    |--------------------------------------------------------------------------
    */

    public function emettre(): void
    {
        $this->statut = 'emise';
        $this->save();
    }

    public function marquerPayee(
        ?string $referencePaiement = null,
        ?string $modePaiement      = null,
        ?string $datePaiement      = null
    ): void {
        // ✅ Vérifier transmission (GereTransmissions maintenant disponible)
        $this->verifierPasEnTransmission('payer');

        $ancienStatut             = $this->statut;
        $this->statut             = 'payee';
        $this->date_paiement      = $datePaiement ?? now();
        $this->reference_paiement = $referencePaiement;
        $this->mode_paiement      = $modePaiement;
        $this->save();

        \App\Models\ActivityLog::logAction($this, 'marquer_payee', [
            'ancien_statut'      => $ancienStatut,
            'nouveau_statut'     => 'payee',
            'reference_paiement' => $referencePaiement,
            'mode_paiement'      => $modePaiement,
            'montant'            => $this->montant_net ?? $this->montant_brut,
            'type'               => $this->type_ordonnance,
        ]);
    }

    public function annuler(): void
    {
        $ancienStatut = $this->statut;
        $this->statut = 'annulee';
        $this->save();

        \App\Models\ActivityLog::logAction($this, 'annuler', [
            'ancien_statut'  => $ancienStatut,
            'nouveau_statut' => 'annulee',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSEURS STATUT
    |--------------------------------------------------------------------------
    */

    public function getStatutLabelAttribute(): string
    {
        return match ($this->statut) {
            'brouillon' => 'Brouillon',
            'emise'     => 'Émise',
            'visee'     => 'Visée',
            'payee'     => 'Payée',
            'annulee'   => 'Annulée',
            default     => $this->statut,
        };
    }

    public function getStatutColorAttribute(): string
    {
        return match ($this->statut) {
            'brouillon' => 'gray',
            'emise'     => 'info',
            'visee'     => 'warning',
            'payee'     => 'success',
            'annulee'   => 'danger',
            default     => 'secondary',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'montant_brut', 'montant_net', 'montant_ir', 'date_paiement', 'reference_paiement'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('workflow')
            ->setDescriptionForEvent(fn(string $event) => match ($event) {
                'created' => "Ordonnance de Paiement créée : {$this->numero}",
                'updated' => "Ordonnance de Paiement modifiée : {$this->numero}",
                'deleted' => "Ordonnance de Paiement supprimée : {$this->numero}",
                default   => "OP {$this->numero} — {$event}",
            });
    }
}
