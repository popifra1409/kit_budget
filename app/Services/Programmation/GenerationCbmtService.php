<?php

namespace App\Services\Programmation;

use App\Models\Budget;
use App\Models\CbmtExercice;
use App\Models\CbmtLigne;
use App\Models\LignePrevisionRecette;
use App\Models\LigneBudgetaire;
use App\Models\PrevisionRecette;
use Illuminate\Support\Facades\DB;

/**
 * Construit le CBMT LIGNE PAR LIGNE à partir de l'exercice de référence (N) :
 *  - ressources : lignes de la prévision de recettes adoptée de N ;
 *  - dépenses   : lignes du budget actif de N ;
 * chacune rangée sous son titre (TitreNomenclatureService).
 *
 * Valeurs de N (toujours rafraîchies) :
 *  - prévision initiale, prévision actualisée (collectifs et virements),
 *  - réalisation : recouvré (ressources) ; engagé ET ordonnancé (dépenses).
 * Projections N+1..N+3 : reconduction de la prévision N actualisée (lignes de référence, LR),
 * écrites à la création seulement — les projections déjà saisies ne sont jamais écrasées,
 * sauf demande explicite ($reinitialiserProjections).
 *
 * Relançable : ajoute les nouvelles lignes de N et met à jour les valeurs de N.
 */
class GenerationCbmtService
{
    public function __construct(protected TitreNomenclatureService $titres) {}

    /** @return array{creees: int, mises_a_jour: int, sans_titre: int, budget: ?string, prevision: ?string} */
    public function generer(CbmtExercice $cbmt, bool $reinitialiserProjections = false): array
    {
        $exercice = $cbmt->exerciceReference;

        if (!$exercice) {
            throw new \DomainException("Le CBMT n'a pas d'exercice de référence (N).");
        }

        $budget = Budget::withoutGlobalScope('exercice')
            ->where('exercice', $exercice->annee)
            ->where('actif', true)
            ->first();

        $prevision = PrevisionRecette::withoutGlobalScope('exercice')
            ->where('exercice_id', $exercice->id)
            ->where('statut', '!=', 'elaboration')
            ->orderByRaw("CASE WHEN statut = 'adopte' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        if (!$budget && !$prevision) {
            throw new \DomainException("Aucun budget actif ni prévision de recettes pour l'exercice {$exercice->annee}.");
        }

        $bilan = [
            'creees' => 0,
            'mises_a_jour' => 0,
            'sans_titre' => 0,
            'budget' => $budget?->code ?? $budget?->libelle,
            'prevision' => $prevision?->code
        ];

        DB::transaction(function () use ($cbmt, $budget, $prevision, $reinitialiserProjections, &$bilan) {
            // ── Ressources ─────────────────────────────────────────
            if ($prevision) {
                $lignes = LignePrevisionRecette::where('prevision_recette_id', $prevision->id)
                    ->with('nomenclature')
                    ->get();

                foreach ($lignes as $l) {
                    $this->enregistrer($cbmt, 'ressource', $l->nomenclature, [
                        'prevision_n_initiale'     => (float) $l->montant_prevu_initial,
                        'montant_n'                => $l->getMontantRectifieReel(),
                        'realisation_n'            => (float) $l->montant_recouvre,
                        'realisation_n_ordonnance' => 0,
                    ], $reinitialiserProjections, $bilan, $l->code_nomenclature, $l->libelle_nomenclature);
                }
            }

            // ── Dépenses ───────────────────────────────────────────
            if ($budget) {
                $lignes = LigneBudgetaire::withoutGlobalScope('exercice')
                    ->where('budget_id', $budget->id)
                    ->with('nomenclature')
                    ->get();

                foreach ($lignes as $l) {
                    $this->enregistrer($cbmt, 'depense', $l->nomenclature, [
                        'prevision_n_initiale'     => (float) $l->budget_initial,
                        'montant_n'                => (float) $l->getBudgetRectifieReel(),
                        'realisation_n'            => (float) $l->engage,
                        'realisation_n_ordonnance' => (float) $l->ordonne,
                    ], $reinitialiserProjections, $bilan);
                }
            }
        });

        return $bilan;
    }

    /** Crée ou met à jour la ligne du CBMT pour un compte. */
    protected function enregistrer(
        CbmtExercice $cbmt,
        string $nature,
        $nomenclature,
        array $valeursN,
        bool $reinitialiserProjections,
        array &$bilan,
        ?string $codeRepli = null,
        ?string $libelleRepli = null
    ): void {
        if (!$nomenclature) {
            return; // ligne sans nomenclature : ignorée
        }

        $titre = $this->titres->titreDe($nomenclature);
        $titres = $nature === 'ressource' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

        $ligne = CbmtLigne::firstOrNew([
            'cbmt_exercice_id' => $cbmt->id,
            'nature'           => $nature,
            'nomenclature_id'  => $nomenclature->id,
        ]);

        $nouvelle = !$ligne->exists;

        $ligne->fill(array_merge($valeursN, [
            'code'          => $nomenclature->code ?? $codeRepli,
            'libelle'       => $nomenclature->libelle ?? $libelleRepli,
            'titre'         => $titre,
            'libelle_titre' => $titre !== null ? ($titres[$titre] ?? null) : null,
            'ordre'         => (int) preg_replace('/\D/', '', (string) ($nomenclature->code ?? '0')),
        ]));

        // Projections : reconduction de la prévision N actualisée (lignes de référence uniquement)
        if ($nouvelle || ($reinitialiserProjections && $ligne->type_ligne !== 'MN')) {
            $ligne->type_ligne = $ligne->type_ligne ?: 'LR';
            $ligne->montant_n_plus_1 = $valeursN['montant_n'];
            $ligne->montant_n_plus_2 = $valeursN['montant_n'];
            $ligne->montant_n_plus_3 = $valeursN['montant_n'];
        }

        $ligne->save();

        $nouvelle ? $bilan['creees']++ : $bilan['mises_a_jour']++;
        if ($titre === null) {
            $bilan['sans_titre']++;
        }
    }
}
