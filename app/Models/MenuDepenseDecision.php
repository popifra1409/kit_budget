<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuDepenseDecision extends Model
{
    protected $table = 'menu_depense_decisions';

    protected $fillable = [
        'regie_avance_id',
        'decision_administrative_id',
        'nomenclature_id',
        'ligne_budgetaire_id',
        'montant_da',
    ];

    protected $casts = [
        'montant_da' => 'decimal:2',
    ];

    public function menuDepense(): BelongsTo
    {
        return $this->belongsTo(RegieAvance::class, 'regie_avance_id');
    }

    public function decisionAdministrative(): BelongsTo
    {
        return $this->belongsTo(DecisionAdministrative::class);
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_budgetaire_id');
    }
}
