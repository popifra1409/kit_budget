<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneEngagement extends Model
{
    use HasFactory;

    protected $table = 'lignes_engagement';

    protected $fillable = [
        'engagement_id',
        'nomenclature_id',
        'numero_ligne',
        'libelle',
        'montant',
        'observations',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'numero_ligne' => 'integer',
    ];

    /**
     * Relation : Engagement parent
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_id');
    }

    /**
     * Relation : Nomenclature budgétaire
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }
}
