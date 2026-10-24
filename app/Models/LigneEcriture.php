<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ligne d'écriture : un compte mouvementé, au débit ou au crédit.
 */
class LigneEcriture extends Model
{
    use HasFactory;

    protected $table = 'lignes_ecritures';

    protected $fillable = [
        'ecriture_comptable_id',
        'compte_comptable_id',
        'compte_code',
        'libelle',
        'debit',
        'credit',
        'tierable_type',
        'tierable_id',
        'date_echeance',
        'lettree_at',
    ];

    protected $casts = [
        'debit'         => 'decimal:2',
        'credit'        => 'decimal:2',
        'date_echeance' => 'date',
        'lettree_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ligne) {
            $compte = $ligne->compte;

            if (!$compte) {
                throw new \Exception('Aucun compte comptable sur cette ligne d\'écriture.');
            }

            // Une classe ou un groupe ne se saisit pas : on impute une feuille.
            if (!$compte->imputable) {
                throw new \Exception(
                    "Le compte {$compte->code} (« {$compte->intitule} ») est un compte de regroupement : "
                        . 'il ne peut pas être mouvementé directement.'
                );
            }

            // Code figé à l'émission : le grand livre reste lisible même après
            // une évolution du plan.
            $ligne->compte_code ??= $compte->code;
        });

        $synchroniser = function (self $ligne) {
            $ligne->ecriture?->synchroniserTotaux();
        };

        static::saved($synchroniser);
        static::deleted($synchroniser);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function ecriture(): BelongsTo
    {
        return $this->belongsTo(EcritureComptable::class, 'ecriture_comptable_id');
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteComptable::class, 'compte_comptable_id');
    }

    /**
     * Tiers concerné (fournisseur, personnel, tiers de recette) — support du
     * lettrage et des âges de créance/dette.
     */
    public function tierable(): MorphTo
    {
        return $this->morphTo('tierable');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function sens(): string
    {
        return (float) $this->credit > 0 ? 'credit' : 'debit';
    }

    public function montant(): float
    {
        return (float) ($this->debit ?: $this->credit);
    }
}
