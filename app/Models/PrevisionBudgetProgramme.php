<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrevisionBudgetProgramme extends Model
{
    protected $table = 'previsions_budget_programme';

    protected $fillable = [
        'exercice_id',
        'nomenclature_id',
        'annee',
        'type',
        'categorie',
        'montant',
        'est_saisi_manuellement',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'montant'                 => 'decimal:2',
        'est_saisi_manuellement'  => 'boolean',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    /**
     * Sauvegarder une prévision (upsert)
     */
    public static function sauvegarder(
        int    $exerciceId,
        int    $nomenclatureId,
        int    $annee,
        string $type,
        float  $montant,
        string $categorie = 'fonctionnement'
    ): self {
        return static::updateOrCreate(
            [
                'exercice_id'    => $exerciceId,
                'nomenclature_id' => $nomenclatureId,
                'annee'          => $annee,
                'type'           => $type,
            ],
            [
                'montant'                => $montant,
                'categorie'              => $categorie,
                'est_saisi_manuellement' => true,
                'created_by'             => auth()->id(),
            ]
        );
    }

    /**
     * Reconduire les prévisions N vers N+1
     * (montant N+1 = montant N * taux de progression)
     */
    public static function reconduireExercice(
        int   $exerciceSourceId,
        int   $exerciceCibleId,
        float $tauxProgression = 1.05  // +5% par défaut
    ): int {
        $previsions = static::where('exercice_id', $exerciceSourceId)
            ->where('type', 'prevision')
            ->get();

        $count = 0;
        foreach ($previsions as $prev) {
            static::updateOrCreate(
                [
                    'exercice_id'     => $exerciceCibleId,
                    'nomenclature_id' => $prev->nomenclature_id,
                    'annee'           => $prev->annee + 1,
                    'type'            => 'prevision',
                ],
                [
                    'montant'                => $prev->montant * $tauxProgression,
                    'categorie'              => $prev->categorie,
                    'est_saisi_manuellement' => false,
                    'observations'           => "Reconduit depuis {$prev->annee} (×{$tauxProgression})",
                ]
            );
            $count++;
        }

        return $count;
    }
}
