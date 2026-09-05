<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

class ActionSousProgramme extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'actions_sous_programmes';

    protected $fillable = [
        'numero',
        'sous_programme_ep_id',
        'code',
        'libelle',
        'description',
        'responsable_id',
        'statut',
        'created_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()->whereYear('created_at', $annee)->count();

        return sprintf('ASP-%d-%05d', $annee, $dernier + 1);
    }

    public function sousProgrammeEp(): BelongsTo
    {
        return $this->belongsTo(SousProgrammeEp::class, 'sous_programme_ep_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function projetsStrategiques(): HasMany
    {
        return $this->hasMany(ProjetStrategique::class, 'action_sous_programme_id');
    }

    public function indicateurs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Indicateur::class, 'indicateurable');
    }
}
