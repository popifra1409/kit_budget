<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenceMercuriale extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercice_id',
        'code_reference',
        'designation',
        'unite',
        'prix_reference',
        'rubrique',
        'sous_rubrique',
        'actif',
    ];

    protected $casts = [
        'prix_reference' => 'decimal:2',
        'actif' => 'boolean',
    ];

    /**
     * Relation avec Exercice
     */
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    /**
     * Scope pour filtrer par exercice actif
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour filtrer par exercice
     */
    public function scopePourExercice($query, $exerciceId)
    {
        return $query->where('exercice_id', $exerciceId);
    }

    /**
     * Affichage formaté
     */
    public function getLibelleCompletAttribute(): string
    {
        return "{$this->code_reference} - {$this->designation} ({$this->unite}) - " .
            number_format($this->prix_reference, 0, ',', ' ') . " FCFA";
    }

    /**
     * Vérifie si la référence mercuriale est modifiable
     * (selon l'état de l'exercice)
     */
    public function estModifiable(): bool
    {
        if (!$this->exercice) {
            return false;
        }

        return $this->exercice->estModifiable();
    }
}
