<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasCorbeille;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObjectifSpecifique extends Model
{
    use HasFactory, HasCorbeille, SoftDeletes;

    protected $table = 'objectifs_specifiques';

    protected $fillable = [
        'action_id',
        'libelle',
        'ordre',
    ];

    protected $casts = [
        'ordre' => 'integer',
    ];

    /**
     * Relation : Action parent
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }
}
