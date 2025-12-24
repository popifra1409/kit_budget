<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectifPrincipal extends Model
{
    use HasFactory;

    protected $table = 'objectifs_principaux';

    protected $fillable = [
        'programme_id',
        'libelle',
        'ordre',
    ];

    protected $casts = [
        'ordre' => 'integer',
    ];

    /**
     * Relation : Programme parent
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
