<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneFactureProforma extends Model
{
    protected $table = 'lignes_factures_proforma';

    protected $fillable = [
        'facture_proforma_id',
        'reference_mercuriale_id',
        'numero_ligne',
        'designation',
        'unite',
        'quantite',
        'prix_unitaire_ht',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_ttc',
        'taux_ir',
        'montant_ir',
        'net_a_percevoir',
        'ligne_bon_commande_id',
        'observations',
    ];

    protected $casts = [
        'quantite'         => 'decimal:3',
        'prix_unitaire_ht' => 'decimal:2',
        'montant_ht'       => 'decimal:2',
        'taux_tva'         => 'decimal:2',
        'montant_tva'      => 'decimal:2',
        'montant_ttc'      => 'decimal:2',
        'taux_ir'          => 'decimal:2',
        'montant_ir'       => 'decimal:2',
        'net_a_percevoir'  => 'decimal:2',
        'numero_ligne'     => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ligne) {
            if (empty($ligne->numero_ligne)) {
                $max = static::where('facture_proforma_id', $ligne->facture_proforma_id)
                    ->max('numero_ligne') ?? 0;
                $ligne->numero_ligne = $max + 1;
            }
            $ligne->calculerMontants();
        });

        static::updating(function ($ligne) {
            $ligne->calculerMontants();
        });

        static::saved(function ($ligne) {
            $ligne->factureProforma?->recalculerTotaux();
        });

        static::deleted(function ($ligne) {
            $ligne->factureProforma?->recalculerTotaux();
        });
    }

    // ── Relations ─────────────────────────────────────────────
    public function factureProforma(): BelongsTo
    {
        return $this->belongsTo(FactureProforma::class, 'facture_proforma_id');
    }

    public function referenceMercuriale(): BelongsTo
    {
        return $this->belongsTo(ReferenceMercuriale::class);
    }

    public function ligneBonCommande(): BelongsTo
    {
        return $this->belongsTo(LigneBonCommande::class);
    }

    // ── Calculs ───────────────────────────────────────────────
    public function calculerMontants(): void
    {
        $this->quantite         = floatval($this->quantite ?? 0);
        $this->prix_unitaire_ht = floatval($this->prix_unitaire_ht ?? 0);
        $this->taux_tva         = floatval($this->taux_tva ?? 19.25);

        $this->montant_ht  = round($this->quantite * $this->prix_unitaire_ht, 2);
        $this->montant_tva = round($this->montant_ht * ($this->taux_tva / 100), 2);
        $this->montant_ttc = round($this->montant_ht + $this->montant_tva, 2);

        // ── IR / Net à Percevoir ──────────────────────────────
        if ($this->taux_ir === null || $this->taux_ir === '') {
            $this->taux_ir = $this->calculerTauxIRAutomatique();
        } else {
            $this->taux_ir = floatval($this->taux_ir);
        }

        $this->montant_ir      = round($this->montant_ht * ($this->taux_ir / 100), 2);
        $this->net_a_percevoir = round($this->montant_ht - $this->montant_ir, 2);
    }

    /**
     * Calcule le taux IR automatiquement — même logique que LigneBonCommande :
     * régime fiscal du fournisseur si disponible, sinon barème par défaut.
     */
    protected function calculerTauxIRAutomatique(): float
    {
        $fournisseur = $this->factureProforma?->fournisseur;

        if ($fournisseur) {
            if (!$fournisseur->relationLoaded('regimeFiscal')) {
                $fournisseur->load('regimeFiscal');
            }
            if ($fournisseur->regimeFiscal && method_exists($fournisseur->regimeFiscal, 'getTauxIRParDefaut')) {
                return (float) $fournisseur->regimeFiscal->getTauxIRParDefaut();
            }
        }

        return $this->calculerTauxIRParDefaut();
    }

    /**
     * Barème IR par défaut (services), identique à LigneBonCommande.
     */
    protected function calculerTauxIRParDefaut(): float
    {
        if ($this->montant_ht < 500000) {
            return 5.5;
        } elseif ($this->montant_ht < 3000000) {
            return 11.0;
        }
        return 15.0;
    }

    // ── Helpers ───────────────────────────────────────────────
    public function estDejaUtilisee(): bool
    {
        return !is_null($this->ligne_bon_commande_id);
    }

    public function estIssueMercuriale(): bool
    {
        return !is_null($this->reference_mercuriale_id);
    }
}
