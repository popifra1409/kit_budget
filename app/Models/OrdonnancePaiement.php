<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasExercice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        'montant_tva' => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'montant_tsr' => 'decimal:2',
        'montant_cnps' => 'decimal:2',
        'montant_irnc' => 'decimal:2',
        'montant_autres_taxes' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($op) {
            if (!$op->numero) {
                $op->numero = $op->genererNumero();
            }

            // Assigner automatiquement le créateur
            if (!$op->created_by) {
                $op->created_by = auth()->id();
            }
        });

        // static::updating(function ($ordonnance) {
        //     $ordonnance->updated_by = auth()->id();
        // });
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
     * ✅ CORRIGÉ : Obtenir le détail des impôts avec TOUTES les taxes DA
     */
    public function getDetailImpots(): array
    {
        if (!$this->relationLoaded('engagement')) {
            $this->load('engagement.engageable');
        }

        $engagement = $this->engagement;

        if (!$engagement) {
            return [
                'ir' => 0,
                'tva' => 0,
                'tsr' => 0,
                'cnps' => 0,
                'irnc' => 0,
                'redevance_av' => 0,
                'feicom' => 0,
                'autres' => 0,
                'total' => 0,
            ];
        }

        $donnees = $engagement->extraireDonneesDocument();

        $ir       = (float) ($donnees['montant_ir']       ?? 0);
        $tva      = (float) ($donnees['montant_tva']      ?? 0);
        $tsr      = (float) ($donnees['montant_tsr']      ?? 0);
        $cnps     = (float) ($donnees['montant_cnps']     ?? 0);
        $irnc     = (float) ($donnees['montant_irnc']     ?? 0);
        $redevance = (float) ($donnees['montant_redevance'] ?? $donnees['montant_redevance_audiovisuelle'] ?? 0);
        $feicom   = (float) ($donnees['montant_feicom']   ?? 0);
        $autres   = (float) ($donnees['autres_retenues']  ?? 0);

        if ($engagement->estBonCommande()) {
            // ── Bon de Commande : IR (retenu à la source) + TVA + TSR ──
            $total = $ir + $tva + $tsr;

            return [
                'ir'          => $ir,
                'tva'         => $tva,
                'tsr'         => $tsr,
                'cnps'        => $cnps,
                'irnc'        => 0,
                'redevance_av' => 0,
                'feicom'      => 0,
                'autres'      => 0,
                'total'       => $total,
            ];
        }

        // ── Décision Administrative : IRNC = IR (même taxe, champ différent) ──
        // On fusionne dans 'ir' pour l'affichage — 'irnc' reste à 0
        // pour éviter tout double-comptage dans le PDF
        $irTotal = $ir + $irnc;  // ← cumul : au cas où les deux seraient renseignés

        $total = $irTotal + $cnps + $tva + $redevance + $feicom + $autres;

        return [
            'ir'          => $irTotal,
            'tva'         => $tva,
            'tsr'         => 0,
            'cnps'        => $cnps,
            'irnc'        => 0,
            'redevance_av' => $redevance,
            'feicom'      => $feicom,
            'autres'      => $autres,
            'total'       => $total,
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

        // ── Fallback séquentiel avec withTrashed ────────────────
        if (!$numeroDocumentSource) {
            return \DB::transaction(function () use ($prefixe, $type) {
                $annee    = now()->format('y');
                $prefixeA = $prefixe . $annee . '-';

                // ✅ withTrashed() — inclure les soft-deleted
                $dernier = static::withTrashed()
                    ->where('numero', 'like', "{$prefixeA}%")
                    ->lockForUpdate()
                    ->orderBy('numero', 'desc')
                    ->first();

                $sequence = 1;
                if ($dernier && preg_match('/(\d+)$/', $dernier->numero, $matches)) {
                    $sequence = intval($matches[1]) + 1;
                }

                return $prefixeA . sprintf('%05d', $sequence);
            });
        }

        // ── Format basé sur le document source ──────────────────
        // Ex: OP-BC26-00001 — pas de séquence, numéro unique par document
        $base = $prefixe . $numeroDocumentSource;

        // ✅ Vérifier si ce numéro existe déjà (soft-deleted inclus)
        $existe = static::withTrashed()->where('numero', $base)->exists();

        if ($existe) {
            // Ajouter un suffixe séquentiel si collision
            return \DB::transaction(function () use ($base) {
                $count = static::withTrashed()
                    ->where('numero', 'like', "{$base}%")
                    ->lockForUpdate()
                    ->count();

                return $base . '-' . ($count + 1);
            });
        }

        return $base;
    }

    public static function genererNumeroEmission(): string
    {
        $annee = now()->format('y'); // 26
        $pattern = "EM{$annee}-%";

        $dernier = static::where('numero_emission', 'like', $pattern)
            ->orderBy('numero_emission', 'desc')
            ->value('numero_emission');

        $sequence = $dernier
            ? ((int) substr($dernier, -5)) + 1
            : 1;

        return sprintf('EM%s-%05d', $annee, $sequence);
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



    public static function creerDepuisBonCommande(BonCommande $bonCommande): self
    {
        return DB::transaction(function () use ($bonCommande) {

            $type = $bonCommande->type_depense === 'impot'
                ? 'impot'
                : 'standard';

            $numeroOP = static::genererNumero($type);

            return static::create([
                // 🔢 Numérotation officielle
                'numero'            => $numeroOP,
                'numero_op'         => $numeroOP,
                'numero_emission'   => static::genererNumeroEmission(),

                // 🔗 Références
                'bon_commande_id'   => $bonCommande->id,
                'budget_id'         => $bonCommande->budget_id,
                'fournisseur_id'    => $bonCommande->fournisseur_id,

                // 💰 Montants
                'montant_brut'      => $bonCommande->montant_total,
                'montant_net'       => $bonCommande->montant_net,

                // 🧾 Métadonnées
                'type_ordonnance'   => $type,
                'statut'            => 'brouillon',
                'date_emission'     => now(),
            ]);
        });
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
