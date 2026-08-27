<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

class CspMinistereSante extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'csp_ministere_sante';

    protected $fillable = [
        'numero', 'code', 'libelle', 'description',
        'periode_debut', 'periode_fin', 'statut', 'created_by',
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
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()
            ->whereYear('created_at', $annee)
            ->count();

        return sprintf('CSP-%d-%05d', $annee, $dernier + 1);
    }

    public function plansStrategiquesEp(): HasMany
    {
        return $this->hasMany(PlanStrategiqueEp::class, 'csp_ministere_id');
    }
}