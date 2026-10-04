<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pièce justificative du service fait, rattachée à une liquidation. */
class LiquidationPreuve extends Model
{
    protected $table = 'liquidation_preuves';

    protected $fillable = [
        'liquidation_id',
        'preuve_service_fait_id',
        'libelle',
        'obligatoire',
        'fourni',
        'reference_document',
        'fichier',
        'ajoute_par',
    ];

    protected $casts = ['obligatoire' => 'boolean', 'fourni' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn(self $p) => $p->ajoute_par ??= auth()->id());

        // Une pièce jointe ou une référence vaut « fournie »
        static::saving(function (self $p) {
            if (filled($p->fichier) || filled($p->reference_document)) {
                $p->fourni = true;
            }
        });
    }

    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(Liquidation::class);
    }
}
