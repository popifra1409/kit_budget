<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneExpressionBesoin extends Model
{
    protected $table = 'lignes_expression_besoins';

    protected $fillable = [
        'expression_besoin_id',
        'article_id',
        'quantite_demandee',
        'quantite_en_stock',
        'quantite_a_commander',
        'prix_unitaire_estime',
        'montant_estime',
        'justification',
        'ordre',
        'disponible_en_stock',
    ];

    protected $casts = [
        'quantite_demandee'    => 'integer',
        'quantite_en_stock'    => 'integer',
        'quantite_a_commander' => 'integer',
        'prix_unitaire_estime' => 'decimal:2',
        'montant_estime'       => 'decimal:2',
        'disponible_en_stock'  => 'boolean',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function expressionBesoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class, 'expression_besoin_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // ====================================
    // BOOT — Renseigner automatiquement le stock
    // ====================================

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($ligne) {
            // Renseigner le stock actuel automatiquement
            $stock = Stock::where('article_id', $ligne->article_id)->first();
            $ligne->quantite_en_stock = $stock?->quantite_disponible ?? 0;

            // Calculer la quantité à commander
            $ligne->quantite_a_commander = max(
                0,
                $ligne->quantite_demandee - $ligne->quantite_en_stock
            );

            // Disponible en stock
            $ligne->disponible_en_stock = $ligne->quantite_en_stock >= $ligne->quantite_demandee;

            // Calculer le montant estimé
            if ($ligne->prix_unitaire_estime > 0) {
                $ligne->montant_estime = round(
                    $ligne->quantite_a_commander * (float)$ligne->prix_unitaire_estime,
                    2
                );
            } elseif ($ligne->article?->prix_unitaire_moyen > 0) {
                $ligne->prix_unitaire_estime = $ligne->article->prix_unitaire_moyen;
                $ligne->montant_estime = round(
                    $ligne->quantite_a_commander * (float)$ligne->prix_unitaire_estime,
                    2
                );
            }
        });
    }
}
