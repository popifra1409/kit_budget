<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FicheConsolidationBesoin extends Model
{
    protected $table = 'fiches_consolidation_besoins';

    protected $fillable = [
        'numero',
        'comptable_matieres_id',
        'date_consolidation',
        'statut',
        'observations',
    ];

    protected $casts = [
        'date_consolidation' => 'date',
    ];

    public function comptableMatieres(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comptable_matieres_id');
    }

    public function expressions(): HasMany
    {
        return $this->hasMany(ExpressionBesoin::class, 'fiche_consolidation_id');
    }

    // ✅ Quantités agrégées par article, toutes expressions confondues
    public function getLignesConsolideesAttribute()
    {
        return $this->expressions->flatMap->lignes
            ->groupBy('article_id')
            ->map(function ($lignes) {
                $premiere = $lignes->first();
                return [
                    'article'              => $premiere->article,
                    'quantite_demandee'    => $lignes->sum('quantite_demandee'),
                    'quantite_en_stock'    => $premiere->article?->quantite_disponible ?? 0,
                    'quantite_a_commander' => $lignes->sum('quantite_a_commander'),
                    'montant_estime'       => $lignes->sum('montant_estime'),
                ];
            })
            ->values();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($fiche) {
            if (!$fiche->numero) {
                $fiche->numero = static::genererNumero();
            }
        });
    }

    public static function genererNumero(): string
    {
        return \DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "FC-{$annee}-";
            $dernier = static::where('numero', 'like', "{$prefixe}%")
                ->lockForUpdate()->orderByDesc('id')->first();
            $seq = $dernier ? intval(substr($dernier->numero, -4)) + 1 : 1;
            return $prefixe . str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
