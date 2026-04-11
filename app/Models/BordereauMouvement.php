<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BordereauMouvement extends Model
{
    use HasFactory;

    protected $table = 'bordereau_mouvements';

    protected $fillable = [
        'bordereau_id',
        'action',
        'effectue_par',
        'date_action',
        'de',
        'vers',
        'commentaire',
    ];

    protected $casts = [
        'date_action' => 'datetime',
    ];

    /**
     * Relation : Bordereau
     */
    public function bordereau(): BelongsTo
    {
        return $this->belongsTo(BordereauEngagement::class, 'bordereau_id');
    }

    /**
     * Relation : Effectué par
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'effectue_par');
    }

    /**
     * Obtenir le libellé de l'action
     */
    public function getLibelleAction(): string
    {
        return match ($this->action) {
            'transmis' => 'Transmis',
            'receptionne' => 'Réceptionné',
            'valide' => 'Validé',
            'rejete' => 'Rejeté',
            'retourne' => 'Retourné',
            'annule' => 'Annulé',
            default => ucfirst($this->action),
        };
    }

    /**
     * Obtenir l'icône de l'action
     */
    public function getIcone(): string
    {
        return match ($this->action) {
            'transmis' => '📤',
            'receptionne' => '📥',
            'valide' => '✅',
            'rejete' => '❌',
            'retourne' => '↩️',
            'annule' => '🚫',
            default => '📋',
        };
    }
}
