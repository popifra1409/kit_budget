<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une valeur datée d'un paramètre d'exécution budgétaire (historique conservé). */
class ParametreExecution extends Model
{
    protected $table = 'parametres_execution';

    protected $fillable = ['cle', 'valeur', 'date_effet', 'motif', 'created_by'];

    protected $casts = ['date_effet' => 'date'];

    protected static function booted(): void
    {
        static::creating(fn(self $p) => $p->created_by ??= auth()->id());
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
