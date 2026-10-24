<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Écriture comptable en-tête : une pièce du budget, une date, un journal, un
 * équilibre débit/crédit porté par ses lignes.
 *
 * source_type/source_id désignent le document SIGB d'origine (bon de commande,
 * dépense de régie, mémoire, liquidation, ordonnance, recette réelle…). L'index
 * unique (source_type, source_id, code_objet) sur les écritures vivantes est ce
 * qui permet de relancer une production automatisée sans jamais comptabiliser
 * deux fois la même pièce.
 */
class EcritureComptable extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ecritures_comptables';

    protected $fillable = [
        'numero',
        'journal_id',
        'exercice_id',
        'date_ecriture',
        'libelle',
        'source_type',
        'source_id',
        'source_numero',
        'code_objet',
        'statut',
        'total_debit',
        'total_credit',
        'equilibree',
        'saisie_par',
        'comptabilisee_at',
        'motif_annulation',
    ];

    protected $casts = [
        'date_ecriture'    => 'date',
        'comptabilisee_at' => 'datetime',
        'total_debit'      => 'decimal:2',
        'total_credit'     => 'decimal:2',
        'equilibree'       => 'boolean',
    ];

    public const STATUT_BROUILLON      = 'brouillon';
    public const STATUT_VALIDEE        = 'validee';
    public const STATUT_COMPTABILISEE  = 'comptabilisee';
    public const STATUT_ANNULEE        = 'annulee';

    public const STATUTS = [
        self::STATUT_BROUILLON,
        self::STATUT_VALIDEE,
        self::STATUT_COMPTABILISEE,
        self::STATUT_ANNULEE,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ecriture) {
            if (blank($ecriture->numero) && $ecriture->journal_id) {
                $ecriture->numero = self::generateNumero(
                    $ecriture->journal_id,
                    $ecriture->date_ecriture ?? now()
                );
            }

            if (blank($ecriture->saisie_par) && auth()->check()) {
                $ecriture->saisie_par = auth()->id();
            }
        });
    }

    /**
     * Numéro EC-2026-0001, par journal et par exercice : une séquence PostgreSQL
     * ne redémarre jamais, même si l'écriture la plus récente est supprimée.
     */
    public static function generateNumero($journalId, $date): string
    {
        $journal = JournalComptable::find($journalId);
        $year    = (int) (\Illuminate\Support\Carbon::parse($date)->format('Y'));
        $prefixe = ($journal?->prefixe_numero ?: 'EC') . '-' . $year . '-';
        $sequence = 'ecritures_comptables_numero_' . preg_replace('/[^A-Za-z0-9_]/', '', $prefixe);

        return DB::transaction(function () use ($prefixe, $sequence) {
            DB::statement("CREATE SEQUENCE IF NOT EXISTS {$sequence}");

            $suite = (int) DB::selectOne("SELECT nextval('{$sequence}') AS suite")->suite;

            $dejaPosee = self::where('numero', 'like', $prefixe . '%')
                ->pluck('numero')
                ->map(fn(string $numero) => (int) substr($numero, strlen($prefixe)))
                ->max() ?? 0;

            if ($suite <= $dejaPosee) {
                DB::statement("SELECT setval('{$sequence}', ?)", [$dejaPosee]);
                $suite = (int) DB::selectOne("SELECT nextval('{$sequence}') AS suite")->suite;
            }

            return $prefixe . str_pad($suite, 4, '0', STR_PAD_LEFT);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalComptable::class, 'journal_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class, 'exercice_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneEcriture::class, 'ecriture_comptable_id')->orderBy('id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeVivantes(Builder $query): Builder
    {
        return $query->where('statut', '!=', self::STATUT_ANNULEE);
    }

    public function scopePeriode(Builder $query, $du, $au): Builder
    {
        return $query
            ->when($du, fn(Builder $q) => $q->whereDate('date_ecriture', '>=', $du))
            ->when($au, fn(Builder $q) => $q->whereDate('date_ecriture', '<=', $au));
    }

    /**
     * Écriture déjà produite pour un document et un objet donnés — la garde
     * d'idempotence des générations automatiques.
     */
    public function scopeDejaComptabiliseePour(Builder $query, string $sourceType, int $sourceId, string $codeObjet): Builder
    {
        return $query->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('code_objet', $codeObjet);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Report des sommes portée par les lignes sur l'en-tête, lu côté base pour
     * que la balance n'exige pas de resommer l'exercice entier.
     */
    public function synchroniserTotaux(): void
    {
        $totaux = $this->lignes()
            ->reorder()
            ->selectRaw('coalesce(sum(debit), 0) as debit, coalesce(sum(credit), 0) as credit')
            ->first();

        $this->equilibree = $totaux
            && (float) $totaux->debit > 0
            && bccomp((string) $totaux->debit, (string) $totaux->credit, 2) === 0;

        $this->updateQuietly([
            'total_debit'  => $totaux->debit  ?? 0,
            'total_credit' => $totaux->credit ?? 0,
            'equilibree'   => $this->equilibree,
        ]);
    }

    /**
     * Une écriture n'est opposable qu'équilibrée : la balance et le compte
     * administratif refusent le reste.
     */
    public function estOpposable(): bool
    {
        return $this->equilibree
            && $this->statut !== self::STATUT_ANNULEE;
    }

    public function estDejaUtiliseePour(string $sourceType, int $sourceId, string $codeObjet): bool
    {
        return static::vivantes()
            ->dejaComptabiliseePour($sourceType, $sourceId, $codeObjet)
            ->exists();
    }
}
