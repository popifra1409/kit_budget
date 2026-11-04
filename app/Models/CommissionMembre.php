<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Membre d'une commission : utilisateur interne ou personne extérieure saisie nommément. */
class CommissionMembre extends Model
{
    protected $table = 'commission_membres';

    protected $fillable = ['commission_id', 'user_id', 'nom', 'titre', 'telephone', 'ordre'];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
