<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneReception extends Model
{
    protected $table = 'lignes_reception';

    protected $fillable = [
        'reception_id',
        'article_id',
        'quantite_commandee',
        'quantite_livree',
        'quantite_conforme',
        'quantite_rejetee',
        'prix_unitaire',
        'montant_total',
        'conforme',
        'motif_rejet',
        'observations',
    ];

    protected $casts = [
        'quantite_commandee' => 'integer',
        'quantite_livree'    => 'integer',
        'quantite_conforme'  => 'integer',
        'quantite_rejetee'   => 'integer',
        'prix_unitaire'      => 'decimal:2',
        'montant_total'      => 'decimal:2',
        'conforme'           => 'boolean',
    ];

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($ligne) {
            // Calculer montant total
            $ligne->montant_total = round(
                $ligne->quantite_conforme * (float)$ligne->prix_unitaire,
                2
            );
            // Quantité rejetée
            $ligne->quantite_rejetee = $ligne->quantite_livree - $ligne->quantite_conforme;
            // Conforme si aucun rejet
            $ligne->conforme = $ligne->quantite_rejetee === 0;
        });
    }
}
