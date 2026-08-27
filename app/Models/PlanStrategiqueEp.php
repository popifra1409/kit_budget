<?php
// app/Models/PlanStrategiqueEp.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

class PlanStrategiqueEp extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'plans_strategiques_ep';

    protected $fillable = [
        'numero',
        'csp_ministere_id',
        'parametres_structure_id',
        'code',
        'libelle',
        'description',
        'periode_debut',
        'periode_fin',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'periode_debut' => 'date',
        'periode_fin' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();
            $model->parametres_structure_id ??= ParametresStructure::getParametres()?->id;
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()
            ->whereYear('created_at', $annee)
            ->count();

        return sprintf('PSE-%d-%05d', $annee, $dernier + 1);
    }

    public function cspMinistere(): BelongsTo
    {
        return $this->belongsTo(CspMinistereSante::class, 'csp_ministere_id');
    }

    public function parametresStructure(): BelongsTo
    {
        return $this->belongsTo(ParametresStructure::class, 'parametres_structure_id');
    }

    public function sousProgrammes(): HasMany
    {
        return $this->hasMany(SousProgrammeEp::class, 'plan_strategique_ep_id');
    }
}
