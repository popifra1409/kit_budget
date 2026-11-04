<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Commission des marchés (paramétrage), rattachée au département qui la porte.
 * C'est un référentiel pérenne : un marché épinglera une commission, il ne recréera pas ses membres.
 */
class Commission extends Model
{
    protected $table = 'commissions';

    protected $fillable = ['code', 'libelle', 'service_id', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function membres(): HasMany
    {
        return $this->hasMany(CommissionMembre::class)->orderBy('ordre');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Le membre ayant la qualité de président — s'il manque, la commission n'est pas opérante. */
    public function president(): HasOne
    {
        return $this->hasOne(CommissionMembre::class)->where('titre', 'president');
    }

    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }
}
