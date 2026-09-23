<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdmtLigne extends Model
{
    protected $table = 'cdmt_lignes';

    protected $fillable = [
        'cdmt_exercice_id',
        'sous_programme_ep_id',
        'action_id',
        'activite_id',
        'libelle',
        'nature',
        'maturite',
        'avant_n_moins_1',
        'n_ae',
        'n_cp',
        'n_plus_1_ae',
        'n_plus_1_cp',
        'n_plus_2_ae',
        'n_plus_2_cp',
        'n_plus_3_ae',
        'n_plus_3_cp',
        'commentaire',
    ];

    protected $casts = [
        'avant_n_moins_1' => 'decimal:2',
        'n_ae' => 'decimal:2',
        'n_cp' => 'decimal:2',
        'n_plus_1_ae' => 'decimal:2',
        'n_plus_1_cp' => 'decimal:2',
        'n_plus_2_ae' => 'decimal:2',
        'n_plus_2_cp' => 'decimal:2',
        'n_plus_3_ae' => 'decimal:2',
        'n_plus_3_cp' => 'decimal:2',
    ];

    public function cdmtExercice(): BelongsTo
    {
        return $this->belongsTo(CdmtExercice::class);
    }

    public function sousProgrammeEp(): BelongsTo
    {
        return $this->belongsTo(SousProgrammeEp::class, 'sous_programme_ep_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'action_id');
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class, 'activite_id');
    }
}
