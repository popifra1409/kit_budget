<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Liquidation d'un engagement : vérification de la dette et arrêt de son montant.
 * Circuit : brouillon → service_fait_certifie → liquidee → visee (rejet : retour en brouillon).
 * Logique métier : App\Services\Budget\LiquidationService
 */
class Liquidation extends Model
{
    use SoftDeletes;

    public const STATUTS = [
        'brouillon'             => 'Brouillon',
        'service_fait_certifie' => 'Service fait certifié',
        'liquidee'              => 'Liquidée',
        'visee'                 => 'Visée (CF)',
    ];

    protected $table = 'liquidations';

    protected $fillable = [
        'numero',
        'exercice_id',
        'engagement_id',
        'nature_service_fait_id',
        'reception_id',
        'montant_liquide',
        'date_service_fait',
        'observations',
        'statut',
        'certifie_par',
        'date_certification',
        'liquide_par',
        'date_liquidation',
        'vise_par',
        'date_visa',
        'motif_rejet',
        'delai_paiement_jours',
        'date_echeance_paiement',
        'controles',
        'created_by',
    ];

    protected $casts = [
        'montant_liquide'        => 'decimal:2',
        'date_service_fait'      => 'date',
        'date_certification'     => 'datetime',
        'date_liquidation'       => 'date',
        'date_visa'              => 'datetime',
        'date_echeance_paiement' => 'date',
        'controles'              => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $l) {
            $l->created_by ??= auth()->id();
            $l->numero ??= static::genererNumero($l->exercice_id);
        });
    }

    /** Numéro LIQ{aa}-{00001}, séquence par exercice. */
    public static function genererNumero(?int $exerciceId): string
    {
        $annee = $exerciceId ? (Exercice::find($exerciceId)?->annee ?? now()->year) : now()->year;
        $prefixe = 'LIQ' . substr((string) $annee, -2) . '-';

        $dernier = static::withTrashed()->where('numero', 'like', "{$prefixe}%")->orderByDesc('numero')->value('numero');
        $suivant = $dernier ? ((int) substr($dernier, -5)) + 1 : 1;

        return $prefixe . str_pad((string) $suivant, 5, '0', STR_PAD_LEFT);
    }

    // ── Relations ───────────────────────────────────────────

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class)->withoutGlobalScope('exercice');
    }

    public function nature(): BelongsTo
    {
        return $this->belongsTo(NatureServiceFait::class, 'nature_service_fait_id');
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function preuves(): HasMany
    {
        return $this->hasMany(LiquidationPreuve::class);
    }

    public function certificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certifie_par');
    }
    public function liquidateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liquide_par');
    }
    public function viseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vise_par');
    }

    // ── État ────────────────────────────────────────────────

    public function getStatutLabelAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /** Preuves obligatoires encore manquantes. */
    public function preuvesManquantes()
    {
        return $this->preuves->where('obligatoire', true)->where('fourni', false)->values();
    }

    /** Jours restants avant l'échéance de paiement (négatif = arriéré). */
    public function joursAvantEcheance(): ?int
    {
        return $this->date_echeance_paiement
            ? (int) Carbon::today()->diffInDays($this->date_echeance_paiement, false)
            : null;
    }
}
