<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValeurIndicateur extends Model
{
    protected $table = 'valeurs_indicateurs';

    protected $fillable = [
        'indicateur_id',
        'periode',
        'valeur_realisee',
        'commentaire',
        'statut',
        'saisi_par_id',
        'valide_par_id',
        'date_validation',
        'created_by',
    ];

    protected $casts = [
        'valeur_realisee' => 'decimal:2',
        'date_validation' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->saisi_par_id ??= auth()->id();
        });
    }

    public function indicateur(): BelongsTo
    {
        return $this->belongsTo(Indicateur::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }

    public function valider(): void
    {
        $this->update([
            'statut' => 'valide',
            'valide_par_id' => auth()->id(),
            'date_validation' => now(),
        ]);
    }

    public function rejeter(?string $motif = null): void
    {
        $this->update([
            'statut' => 'rejete',
            'commentaire' => $motif ?? $this->commentaire,
        ]);
    }
}
