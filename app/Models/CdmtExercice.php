<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;

class CdmtExercice extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'cdmt_exercices';

    protected $fillable = [
        'numero',
        'cbmt_exercice_id',
        'version',
        'date_cdmt_initial',
        'date_cdmt_final',
        'note_arbitrages',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'date_cdmt_initial' => 'date',
        'date_cdmt_final' => 'date',
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

        return sprintf('CDMT-%d-%05d', $annee, $dernier + 1);
    }

    public function cbmtExercice(): BelongsTo
    {
        return $this->belongsTo(CbmtExercice::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CdmtLigne::class);
    }

    public function ppaExercices(): HasMany
    {
        return $this->hasMany(PpaExercice::class);
    }

    /**
     * Synthese par sous-programme (Annexe B/C), pour N+1/N+2/N+3.
     */
    public function getSyntheseParSousProgramme(): \Illuminate\Support\Collection
    {
        return $this->lignes()
            ->with('sousProgrammeEp')
            ->get()
            ->groupBy('sous_programme_ep_id')
            ->map(function ($lignes) {
                return [
                    'sous_programme' => $lignes->first()->sousProgrammeEp,
                    'n_plus_1_ae' => $lignes->sum('n_plus_1_ae'),
                    'n_plus_1_cp' => $lignes->sum('n_plus_1_cp'),
                    'n_plus_2_ae' => $lignes->sum('n_plus_2_ae'),
                    'n_plus_2_cp' => $lignes->sum('n_plus_2_cp'),
                    'n_plus_3_ae' => $lignes->sum('n_plus_3_ae'),
                    'n_plus_3_cp' => $lignes->sum('n_plus_3_cp'),
                ];
            })
            ->values();
    }

    /**
     * Test de soutenabilite CDMT <-> CBMT (I.3 du guide) :
     * compare le total programme (CP) au plafond de depenses du CBMT.
     */
    public function getEcartAvecCbmt(): array
    {
        $depensesCbmt = $this->cbmtExercice->lignesDepenses()->get();
        $totalPlafondN1 = $depensesCbmt->sum('montant_n_plus_1');
        $totalPlafondN2 = $depensesCbmt->sum('montant_n_plus_2');
        $totalPlafondN3 = $depensesCbmt->sum('montant_n_plus_3');

        $totalProgrammeN1 = $this->lignes()->sum('n_plus_1_cp');
        $totalProgrammeN2 = $this->lignes()->sum('n_plus_2_cp');
        $totalProgrammeN3 = $this->lignes()->sum('n_plus_3_cp');

        return [
            'n_plus_1' => ['plafond' => $totalPlafondN1, 'programme' => $totalProgrammeN1, 'ecart' => $totalPlafondN1 - $totalProgrammeN1],
            'n_plus_2' => ['plafond' => $totalPlafondN2, 'programme' => $totalProgrammeN2, 'ecart' => $totalPlafondN2 - $totalProgrammeN2],
            'n_plus_3' => ['plafond' => $totalPlafondN3, 'programme' => $totalProgrammeN3, 'ecart' => $totalPlafondN3 - $totalProgrammeN3],
        ];
    }
}
