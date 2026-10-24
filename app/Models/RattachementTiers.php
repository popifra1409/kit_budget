<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Dans quel compte de tiers un tiers du budget s'impute.
 *
 * Les tiers existent déjà (fournisseurs, personnels, tiers de recette) : cette
 * table ne fait que les raccorder au plan SYSCOHADA. Sans rattachement posé,
 * comptePour() retombe sur le compte par défaut de la famille (401, 421, 411),
 * ce qui laisse la saisie automatisée proposer un compte même au démarrage.
 */
class RattachementTiers extends Model
{
    use HasFactory;

    protected $table = 'rattachements_tiers';

    protected $fillable = [
        'tierable_type',
        'tierable_id',
        'compte_comptable_id',
        'compte_code',
        'rattache_par',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $rattachement) {
            if (!$rattachement->compte) {
                return;
            }

            $rattachement->compte_code ??= $rattachement->compte->code;

            if (blank($rattachement->rattache_par) && auth()->check()) {
                $rattachement->rattache_par = auth()->id();
            }
        });
    }

    public function tierable(): MorphTo
    {
        return $this->morphTo('tierable');
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteComptable::class, 'compte_comptable_id');
    }

    public static function pour(\Illuminate\Database\Eloquent\Model $tier): ?self
    {
        return static::where('tierable_type', $tier::class)
            ->where('tierable_id', $tier->getKey())
            ->first();
    }

    /**
     * Le compte du tiers, rattachement posé ou compte par défaut de sa famille.
     */
    public static function comptePour(\Illuminate\Database\Eloquent\Model $tier): ?CompteComptable
    {
        $compte = static::pour($tier)?->compte;

        if ($compte) {
            return $compte;
        }

        $typeTiers = CompteComptable::TYPE_TIERS_PAR_MODELE[$tier::class] ?? null;

        return $typeTiers
            ? CompteComptable::compteDefautTiers($typeTiers)
            : null;
    }
}
