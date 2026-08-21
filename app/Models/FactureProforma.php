<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FactureProforma extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'factures_proforma';

    protected $fillable = [
        'numero',
        'exercice_id',
        'fournisseur_id',
        'date_facture',
        'objet',
        'montant_ht',
        'montant_tva',
        'montant_ir',
        'montant_ttc',
        'net_a_percevoir',
        'statut',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'date_facture'    => 'date',
        'montant_ht'      => 'decimal:2',
        'montant_tva'     => 'decimal:2',
        'montant_ir'      => 'decimal:2',
        'montant_ttc'     => 'decimal:2',
        'net_a_percevoir' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($facture) {
            if (empty($facture->numero)) {
                $facture->numero = static::genererNumero();
            }
            $facture->created_by = auth()->id();
        });
    }

    // ── Numérotation ──────────────────────────────────────────
    public static function genererNumero(): string
    {
        $annee = substr((string) now()->year, -2);
        $prefix = "FP{$annee}";

        $result = \DB::selectOne("
            SELECT COALESCE(MAX(CAST(SPLIT_PART(numero, '-', 2) AS INTEGER)), 0) AS max_seq
            FROM factures_proforma
            WHERE numero LIKE :pattern
        ", ['pattern' => "{$prefix}-%"]);

        return sprintf('%s-%05d', $prefix, ($result->max_seq ?? 0) + 1);
    }

    // ── Relations ─────────────────────────────────────────────
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFactureProforma::class, 'facture_proforma_id')->orderBy('numero_ligne');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Méthodes ──────────────────────────────────────────────

    /**
     * Recalcule les totaux depuis les lignes
     */
    public function recalculerTotaux(): void
    {
        $this->updateQuietly([
            'montant_ht'      => $this->lignes()->sum('montant_ht'),
            'montant_tva'     => $this->lignes()->sum('montant_tva'),
            'montant_ir'      => $this->lignes()->sum('montant_ir'),
            'montant_ttc'     => $this->lignes()->sum('montant_ttc'),
            'net_a_percevoir' => $this->lignes()->sum('net_a_percevoir'),
        ]);
    }

    /**
     * Nombre de lignes déjà reprises dans un bon de commande
     */
    public function getNombreLignesUtiliseesAttribute(): int
    {
        return $this->lignes()->whereNotNull('ligne_bon_commande_id')->count();
    }

    /**
     * Toutes les lignes ont-elles été reprises dans un BC ?
     */
    public function estEntierementUtilisee(): bool
    {
        return $this->lignes()->whereNull('ligne_bon_commande_id')->doesntExist();
    }

    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }
}
