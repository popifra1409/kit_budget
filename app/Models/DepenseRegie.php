<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepenseRegie extends Model
{
    use SoftDeletes;

    protected $table = 'depenses_regies';

    protected $fillable = [
        'regie_avance_id',
        'decaissement_regie_id',
        'ligne_regie_avance_id',
        'numero',
        'date_depense',
        'objet',
        'type_depense',
        'fournisseur_id',
        'fournisseur_libre',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_ttc',
        'taux_ir',
        'montant_ir',
        'net_a_payer',
        'statut',
        'justificatif_fichier',
        'observations',
        'created_by',
        'updated_by',
        'mode_saisie',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant_ht'   => 'decimal:2',
        'montant_tva'  => 'decimal:2',
        'montant_ttc'  => 'decimal:2',
        'montant_ir'   => 'decimal:2',
        'net_a_payer'  => 'decimal:2',
        'taux_tva'     => 'decimal:2',
        'taux_ir'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($depense) {
            if (!$depense->numero) {
                $depense->numero = static::genererNumero($depense->regieAvance);
            }
            $depense->created_by = auth()->id();
        });

        static::saved(function ($depense) {
            // Recalculer les montants de la ligne et de la régie
            $depense->ligneRegieAvance?->recalculerMontants();
            $depense->regieAvance?->recalculerMontants();
            $depense->decaissementRegie?->recalculerDepenses();
        });

        static::deleted(function ($depense) {
            $depense->ligneRegieAvance?->recalculerMontants();
            $depense->regieAvance?->recalculerMontants();
        });
    }

    // ── Relations ─────────────────────────────────────────────
    public function regieAvance(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function decaissementRegie(): BelongsTo
    {
        return $this->belongsTo(DecaissementRegie::class, 'decaissement_regie_id');
    }

    public function ligneRegieAvance(): BelongsTo
    {
        return $this->belongsTo(LigneRegieAvance::class, 'ligne_regie_avance_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function bonCommandeRegie(): BelongsTo
    {
        return $this->belongsTo(BonCommandeRegie::class, 'bon_commande_regie_id');
    }

    public function lignes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LigneDepenseRegie::class)->orderBy('numero_ligne');
    }
    
    // ── Numérotation ──────────────────────────────────────────
    public static function genererNumero(RegieAvance $regie): string
    {
        $annee  = substr($regie->exercice->annee ?? now()->year, -2);
        $prefix = match ($regie->type) {
            'rav'          => "DR{$annee}",
            'menu_depense' => "DM{$annee}",
            default        => "DR{$annee}",
        };

        $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM depenses_regies
            WHERE numero LIKE :pattern
        ", ['pattern' => "{$prefix}-%"]);

        return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
    }

    // ── Méthodes ──────────────────────────────────────────────
    public function estAchatDirect(): bool
    {
        return $this->type_depense === 'achat_direct';
    }

    public function estBonCommande(): bool
    {
        return $this->type_depense === 'bon_commande';
    }
}
