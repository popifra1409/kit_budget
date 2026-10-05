<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Paiement sans ordonnancement préalable (procédure exceptionnelle).
 * Circuit : brouillon → autorise → paye → regularise (ou annule avant paiement).
 * Logique métier : App\Services\Budget\PaiementExceptionnelService
 */
class PaiementExceptionnel extends Model
{
    use SoftDeletes;

    public const STATUTS = [
        'brouillon'  => 'Brouillon',
        'autorise'   => 'Autorisé',
        'paye'       => 'Payé — à régulariser',
        'regularise' => 'Régularisé',
        'annule'     => 'Annulé',
    ];

    protected $table = 'paiements_exceptionnels';

    protected $fillable = [
        'numero',
        'exercice_id',
        'ligne_budgetaire_id',
        'beneficiaire_type',
        'beneficiaire_id',
        'montant',
        'objet',
        'motif_urgence',
        'justification',
        'fondement',
        'autorise_par',
        'reference_autorisation',
        'date_autorisation',
        'piece_autorisation',
        'paye_par',
        'date_paiement',
        'mode_paiement',
        'reference_paiement',
        'piece_paiement',
        'delai_regularisation_jours',
        'date_limite_regularisation',
        'ordonnance_paiement_id',
        'regularise_par',
        'date_regularisation',
        'observations',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'montant'                    => 'decimal:2',
        'date_autorisation'          => 'date',
        'date_paiement'              => 'date',
        'date_limite_regularisation' => 'date',
        'date_regularisation'        => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->created_by ??= auth()->id();
            $p->exercice_id ??= Exercice::getActif()?->id;
            $p->numero ??= static::genererNumero($p->exercice_id);
        });
    }

    /** Numéro PEX{aa}-{00001}, séquence par exercice. */
    public static function genererNumero(?int $exerciceId): string
    {
        $annee = $exerciceId ? (Exercice::find($exerciceId)?->annee ?? now()->year) : now()->year;
        $prefixe = 'PEX' . substr((string) $annee, -2) . '-';
        $dernier = static::withTrashed()->where('numero', 'like', "{$prefixe}%")->orderByDesc('numero')->value('numero');

        return $prefixe . str_pad((string) (($dernier ? (int) substr($dernier, -5) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class)->withoutGlobalScope('exercice');
    }

    public function beneficiaire(): MorphTo
    {
        return $this->morphTo();
    }

    public function ordonnancePaiement(): BelongsTo
    {
        return $this->belongsTo(OrdonnancePaiement::class);
    }

    public function autorisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorise_par');
    }
    public function payeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paye_par');
    }
    public function regularisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regularise_par');
    }

    public function getStatutLabelAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getNomBeneficiaireAttribute(): string
    {
        $b = $this->beneficiaire;

        return $b?->raison_sociale ?? $b?->nom_complet ?? '—';
    }

    /** Jours restants avant la date limite de régularisation (négatif = en retard). */
    public function joursAvantRegularisation(): ?int
    {
        return $this->statut === 'paye' && $this->date_limite_regularisation
            ? (int) Carbon::today()->diffInDays($this->date_limite_regularisation, false)
            : null;
    }

    public function estEnRetard(): bool
    {
        return ($j = $this->joursAvantRegularisation()) !== null && $j < 0;
    }
}
