<?php

namespace App\Models;

use App\Services\Budget\ControlePlafondCbmt;
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
     * Reconduire le plan triennal vers l'exercice suivant (montant × taux de progression).
     *
     * Chaque projection porte sa propre année visée (N+1 → N+2, N+2 → N+3) sous l'exercice cible,
     * donc le lot est regroupé PAR ANNÉE VISÉE : le plafond CBMT s'oppose titre par titre ET année
     * par année, et une reconduction qui déborderait 2029 ne doit pas empêcher 2028 de passer.
     *
     * @return array{ecrits: int, refus: array<int, array>, source: int, cible: int}
     */
    public static function reconduireExercice(
        int   $exerciceSourceId,
        int   $exerciceCibleId,
        float $tauxProgression = 1.05  // +5% par défaut
    ): array {
        $controlle = app(ControlePlafondCbmt::class);

        $previsions = static::with('nomenclature')
            ->where('exercice_id', $exerciceSourceId)
            ->where('type', 'prevision')
            ->get();

        // Lot par année visée et par nature, la nature étant portée par la nomenclature.
        $lots = [];
        foreach ($previsions as $prev) {
            $nature = $prev->nomenclature->type ?? null;
            $lots[(int) $prev->annee + 1][($nature === 'recette' ? 'recette' : 'depense')][] = [
                'nomenclature_id' => (int) $prev->nomenclature_id,
                'montant' => round((float) $prev->montant * $tauxProgression, 2),
                'categorie' => (string) $prev->categorie,
                'annee_source' => (int) $prev->annee,
            ];
        }

        $ecrits = 0;
        $refus = [];

        foreach ($lots as $annee => $parNature) {
            foreach ($parNature as $nature => $lot) {
                $decision = $controlle->decider($nature, $annee, $lot, $exerciceCibleId);

                foreach ($decision['acceptees'] as $ligne) {
                    static::updateOrCreate(
                        [
                            'exercice_id'     => $exerciceCibleId,
                            'nomenclature_id' => $ligne['nomenclature_id'],
                            'annee'           => $annee,
                            'type'            => 'prevision',
                        ],
                        [
                            'montant'                => $ligne['montant'],
                            'categorie'              => $ligne['categorie'],
                            'est_saisi_manuellement' => false,
                            'observations'           => "Reconduit depuis {$ligne['annee_source']} (×{$tauxProgression})",
                        ]
                    );
                    $ecrits++;
                }

                foreach ($decision['refus'] as $r) {
                    $refus[] = $r + ['nature' => $nature];
                }
            }
        }

        return ['ecrits' => $ecrits, 'refus' => $refus,
            'source' => $exerciceSourceId, 'cible' => $exerciceCibleId];
    }
}
