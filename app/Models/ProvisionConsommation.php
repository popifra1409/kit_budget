<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace combien un BonCommandeRegie a consommé sur chaque ProvisionLigneRegie.
 * Permet la consommation "en cascade" (plusieurs décaissements de la même
 * ligne consommés simultanément) et un désengagement précis (crédite
 * chaque provision exactement du montant qui lui avait été pris).
 */
class ProvisionConsommation extends Model
{
    protected $table = 'provision_consommations';

    protected $fillable = [
        'provision_ligne_regie_id',
        'bon_commande_regie_id',
        'montant',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function provisionLigneRegie(): BelongsTo
    {
        return $this->belongsTo(ProvisionLigneRegie::class, 'provision_ligne_regie_id');
    }

    public function bonCommandeRegie(): BelongsTo
    {
        return $this->belongsTo(BonCommandeRegie::class, 'bon_commande_regie_id');
    }
}
