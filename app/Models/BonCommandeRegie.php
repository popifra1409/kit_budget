<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonCommandeRegie extends Model
{
    use SoftDeletes;

    protected $table = 'bons_commande_regies';

    protected $fillable = [
        'regie_avance_id',
        'depense_regie_id',
        'numero',
        'date_emission',
        'objet',
        'fournisseur_id',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'montant_ir',
        'net_a_payer',
        'statut',
        'observations',
        'created_by',
        'updated_by',
        'ligne_regie_avance_id',
        'provision_ligne_regie_id',
        'engage',
        'date_engagement',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'montant_ht'    => 'decimal:2',
        'montant_tva'   => 'decimal:2',
        'montant_ttc'   => 'decimal:2',
        'montant_ir'    => 'decimal:2',
        'net_a_payer'   => 'decimal:2',
        'engage'           => 'boolean',
        'date_engagement'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($bc) {
            if (!$bc->numero) {
                $bc->numero = static::genererNumero($bc->regieAvance);
            }
            $bc->created_by = auth()->id();
        });

        static::updating(function ($bc) {
            $bc->updated_by = auth()->id();
        });
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function depenseRegie(): BelongsTo
    {
        return $this->belongsTo(DepenseRegie::class, 'depense_regie_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommandeRegie::class, 'bon_commande_regie_id');
    }

    // ── Numérotation ──────────────────────────────────────────
    public static function genererNumero(RegieAvance $regie): string
    {
        $annee  = substr($regie->exercice->annee ?? now()->year, -2);
        $prefix = match ($regie->type) {
            'rav'          => "BCR{$annee}",
            'menu_depense' => "BCM{$annee}",
            default        => "BCR{$annee}",
        };

        $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM bons_commande_regies
            WHERE numero LIKE :pattern
        ", ['pattern' => "{$prefix}-%"]);

        return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
    }

    // ── Recalcul totaux depuis lignes ─────────────────────────
    public function recalculerTotaux(): void
    {
        $this->updateQuietly([
            'montant_ht'  => $this->lignes()->sum('montant_ht'),
            'montant_tva' => $this->lignes()->sum('montant_tva'),
            'montant_ttc' => $this->lignes()->sum('montant_ttc'),
            'montant_ir'  => $this->lignes()->sum('montant_ir'),
            'net_a_payer' => $this->lignes()->sum('net_a_payer'),
        ]);
    }


    public function ligneRegieAvance(): BelongsTo
    {
        return $this->belongsTo(LigneRegieAvance::class, 'ligne_regie_avance_id');
    }

    public function provisionLigneRegie(): BelongsTo
    {
        return $this->belongsTo(ProvisionLigneRegie::class, 'provision_ligne_regie_id');
    }

    // ── Engagement ────────────────────────────────────────────────
    public function engager(): void
    {
        if ($this->engage) {
            throw new \Exception("Ce bon de commande est déjà engagé.");
        }
        if ($this->statut !== 'valide') {
            throw new \Exception("Le BCR doit être validé avant engagement.");
        }

        $provision = $this->provisionLigneRegie;
        if (!$provision) {
            throw new \Exception("Aucune provision associée à ce bon de commande.");
        }

        // Débiter la provision
        $provision->debiter($this->montant_ttc);

        $this->updateQuietly([
            'engage'          => true,
            'date_engagement' => now(),
        ]);
    }

    public function desengager(): void
    {
        if (!$this->engage) {
            throw new \Exception("Ce BCR n'est pas engagé.");
        }

        $this->provisionLigneRegie?->crediter($this->montant_ttc);

        $this->updateQuietly([
            'engage'          => false,
            'date_engagement' => null,
        ]);
    }
}
