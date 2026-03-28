<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $table = 'stocks';

    protected $fillable = [
        'article_id',
        'quantite_disponible',
        'quantite_reservee',
        'quantite_commandee',
        'valeur_stock',
        'emplacement',
        'date_dernier_mouvement',
        'date_inventaire',
        'quantite_inventaire',
        'observations',
    ];

    protected $casts = [
        'quantite_disponible'  => 'integer',
        'quantite_reservee'    => 'integer',
        'quantite_commandee'   => 'integer',
        'valeur_stock'         => 'decimal:2',
        'date_dernier_mouvement' => 'date',
        'date_inventaire'      => 'date',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    public function getQuantiteTotaleAttribute(): int
    {
        return $this->quantite_disponible + $this->quantite_reservee;
    }

    public function getEstCritiqueAttribute(): bool
    {
        return $this->quantite_disponible <= ($this->article?->seuil_alerte ?? 0);
    }

    // ====================================
    // MÉTHODES
    // ====================================

    public function entree(int $quantite, float $prixUnitaire = 0): void
    {
        $this->quantite_disponible    += $quantite;
        $this->valeur_stock            = $this->quantite_disponible * $prixUnitaire;
        $this->date_dernier_mouvement  = now()->toDateString();
        $this->saveQuietly();
    }

    public function sortie(int $quantite): void
    {
        if ($quantite > $this->quantite_disponible) {
            throw new \Exception(
                "Stock insuffisant pour {$this->article?->designation}. " .
                    "Disponible : {$this->quantite_disponible}, Demandé : {$quantite}"
            );
        }
        $this->quantite_disponible   -= $quantite;
        $this->date_dernier_mouvement = now()->toDateString();
        $this->saveQuietly();
    }

    public function reserver(int $quantite): void
    {
        $this->quantite_reservee  += $quantite;
        $this->quantite_disponible -= $quantite;
        $this->saveQuietly();
    }
}
