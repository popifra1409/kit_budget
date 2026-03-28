<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FicheStock extends Model
{
    protected $table = 'fiches_stock';

    protected $fillable = [
        'numero',
        'article_id',
        'exercice_id',
        'type_mouvement',
        'quantite',
        'prix_unitaire',
        'valeur_totale',
        'stock_avant',
        'stock_apres',
        'date_mouvement',
        'motif',
        'reference_document',
        'document_type',
        'document_id',
        'created_by',
    ];

    protected $casts = [
        'quantite'        => 'integer',
        'stock_avant'     => 'integer',
        'stock_apres'     => 'integer',
        'prix_unitaire'   => 'decimal:2',
        'valeur_totale'   => 'decimal:2',
        'date_mouvement'  => 'date',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function document(): MorphTo
    {
        return $this->morphTo('document');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
 
    // ====================================
    // MÉTHODE FACTORY
    // ====================================

    /**
     * Créer un mouvement de stock et mettre à jour le stock courant
     */
    public static function enregistrerMouvement(
        Article $article,
        string  $type,
        int     $quantite,
        float   $prixUnitaire = 0,
        array   $meta = []
    ): self {
        $stock     = $article->stock ?? Stock::where('article_id', $article->id)->first();
        $stockAvant = $stock?->quantite_disponible ?? 0;

        $stockApres = match ($type) {
            'entree', 'retour', 'ajustement' => $stockAvant + $quantite,
            'sortie', 'transfert'            => $stockAvant - $quantite,
            default                          => $stockAvant,
        };

        $fiche = static::create([
            'numero'             => static::genererNumero(),
            'article_id'         => $article->id,
            'exercice_id'        => $meta['exercice_id'] ?? \App\Models\Exercice::getActif()?->id,
            'type_mouvement'     => $type,
            'quantite'           => $quantite,
            'prix_unitaire'      => $prixUnitaire,
            'valeur_totale'      => round($quantite * $prixUnitaire, 2),
            'stock_avant'        => $stockAvant,
            'stock_apres'        => $stockApres,
            'date_mouvement'     => $meta['date'] ?? now()->toDateString(),
            'motif'              => $meta['motif'] ?? null,
            'reference_document' => $meta['reference'] ?? null,
            'document_type'      => $meta['document_type'] ?? null,
            'document_id'        => $meta['document_id'] ?? null,
            'created_by'         => auth()->id(),
        ]);

        // Mettre à jour le stock courant
        if ($stock) {
            match ($type) {
                'entree', 'retour' => $stock->entree($quantite, $prixUnitaire),
                'sortie'           => $stock->sortie($quantite),
                'ajustement'       => (function () use ($stock, $stockApres, $prixUnitaire) {
                    $stock->quantite_disponible = $stockApres;
                    $stock->valeur_stock = $stockApres * $prixUnitaire;
                    $stock->date_dernier_mouvement = now()->toDateString();
                    $stock->saveQuietly();
                })(),
                default => null,
            };
        }

        return $fiche;
    }

    private static function genererNumero(): string
    {
        return \DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "FS-{$annee}-";
            $dernier = static::where('numero', 'like', "{$prefixe}%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            $seq = $dernier ? intval(substr($dernier->numero, -6)) + 1 : 1;
            return $prefixe . str_pad($seq, 6, '0', STR_PAD_LEFT);
        });
    }
}
