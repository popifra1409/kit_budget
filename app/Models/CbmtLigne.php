<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbmtLigne extends Model
{
    protected $table = 'cbmt_lignes';

    protected $fillable = [
        'cbmt_exercice_id',
        'nature',
        'titre',
        'libelle_titre',
        'source',
        'montant_n_moins_1',
        'montant_n',
        'montant_n_plus_1',
        'montant_n_plus_2',
        'montant_n_plus_3',
    ];

    protected $casts = [
        'montant_n_moins_1' => 'decimal:2',
        'montant_n' => 'decimal:2',
        'montant_n_plus_1' => 'decimal:2',
        'montant_n_plus_2' => 'decimal:2',
        'montant_n_plus_3' => 'decimal:2',
    ];

    public function cbmtExercice(): BelongsTo
    {
        return $this->belongsTo(CbmtExercice::class);
    }

    /** Libelles standards des titres de ressources (Tableau 9) */
    public const TITRES_RESSOURCES = [
        1 => 'Recettes fiscales affectées',
        2 => "Produits de l'exploitation du domaine et des services",
        3 => 'Dotations et subventions',
        4 => 'Autres recettes',
    ];

    /** Libelles standards des titres de depenses (Tableau 10) */
    public const TITRES_DEPENSES = [
        1 => 'Charges financières de la dette',
        2 => 'Dépenses de personnel',
        3 => 'Dépenses de biens et services',
        4 => 'Dépenses de subvention et de transfert',
        5 => "Dépenses d'investissement",
        6 => 'Autres dépenses de fonctionnement',
    ];
}
