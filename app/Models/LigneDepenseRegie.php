<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneDepenseRegie extends Model
{
    protected $table = 'lignes_depenses_regies';

    protected $fillable = [
        'depense_regie_id',
        'numero_ligne',
        'nature_depense',
        'quantite',
        'prix_unitaire',
        'montant_nap_input',
        'taux_tva',
        'taux_ir',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'montant_ir',
        'montant_net',
        'observations',
    ];

    protected $casts = [
        'quantite'          => 'float',
        'prix_unitaire'     => 'float',
        'montant_nap_input' => 'float',
        'taux_tva'          => 'float',
        'taux_ir'           => 'float',
        'montant_ht'        => 'float',
        'montant_tva'       => 'float',
        'montant_ttc'       => 'float',
        'montant_ir'        => 'float',
        'montant_net'       => 'float',
    ];

    public function depenseRegie(): BelongsTo
    {
        return $this->belongsTo(DepenseRegie::class);
    }
}
