<?php

namespace App\Services\Planification;

use App\Models\Action;
use App\Models\Exercice;
use App\Models\PlanStrategiqueEp;
use App\Models\SousProgrammeEp;
use Illuminate\Support\Collection;

/**
 * Cadre logique de la programmation triennale (CDMT) — modèle « Proposition de cadre
 * stratégique de performance » :
 *
 *  - Tableau 1 : Cadre Stratégique des Sous-programmes  -> cadreStrategique()
 *  - Tableaux 2..N : Matrice du Cadre Logique du Sous-Programme k -> matrices()
 *
 * Les colonnes d'années suivent l'exercice de référence N de l'EP :
 * réalisation (N-1), estimation (N), valeurs cibles (N+1, N+2, N+3).
 */
class CadreLogiqueSousProgrammes
{
    public function __construct(
        public readonly PlanStrategiqueEp $psp,
        public readonly Exercice $exercice,
        public readonly ?SousProgrammeEp $sousProgramme = null,
    ) {}

    public function anneeReference(): int
    {
        return (int) $this->exercice->annee;
    }

    /** Le rapport est-il restreint à un seul sous-programme (pas de tableau 1) ? */
    public function filtreUnSousProgramme(): bool
    {
        return $this->sousProgramme !== null;
    }

    /** @return int[] Les cinq années du tableau, dans l'ordre des colonnes */
    public function annees(): array
    {
        $n = $this->anneeReference();

        return [$n - 1, $n, $n + 1, $n + 2, $n + 3];
    }

    public function sousProgrammes(): Collection
    {
        return $this->psp->sousProgrammes()
            ->with('indicateurs.valeurs')
            ->when($this->sousProgramme, fn($q) => $q->whereKey($this->sousProgramme->getKey()))
            ->orderBy('code')
            ->get();
    }

    /** Tableau 1 : un bloc par sous-programme, lignes = indicateurs du sous-programme. */
    public function cadreStrategique(): Collection
    {
        return $this->sousProgrammes()
            ->map(fn(SousProgrammeEp $sp) => $this->bloc($sp, $this->titreSousProgramme($sp)));
    }

    /**
     * Matrices : pour chaque sous-programme, le bloc du sous-programme puis un bloc
     * par action (Tableau « Sous-programme/Actions »).
     */
    public function matrices(): Collection
    {
        return $this->sousProgrammes()->map(function (SousProgrammeEp $sp) {
            $actions = $sp->actionsPourExercice($this->exercice->id)
                ->with(['objectifsSpecifiques', 'indicateurs.valeurs', 'responsable'])
                ->get();

            return [
                'sous_programme' => $sp,
                'bloc' => $this->bloc($sp, $this->titreSousProgramme($sp)),
                'actions' => $actions->map(fn(Action $action) => $this->bloc(
                    $action,
                    "Action {$action->code} : {$action->libelle}"
                )),
            ];
        });
    }

    public function titreSousProgramme(SousProgrammeEp $sp): string
    {
        return trim("{$sp->code} : {$sp->libelle}");
    }

    /**
     * Bloc du tableau : intitulé, objectif, responsable et une ligne par indicateur.
     *
     * @return array{titre: string, objectif: ?string, responsable: ?string, lignes: array}
     */
    protected function bloc(SousProgrammeEp|Action $modele, string $titre): array
    {
        $annees = $this->annees();
        $reference = $this->anneeReference();

        return [
            'titre' => $titre,
            'objectif' => $modele->objectifPourCadreLogique(),
            'responsable' => $modele->responsable?->name,
            'lignes' => $modele->indicateurs
                ->sortBy('code')
                ->map(fn($indicateur) => [
                    'indicateur' => $indicateur->libelle,
                    'unite' => $indicateur->unite_mesure,
                    'source' => $indicateur->source_verification,
                    'valeurs' => array_map(
                        fn(int $annee) => $indicateur->valeurPourAnnee($annee, $reference),
                        array_combine($annees, $annees)
                    ),
                ])
                ->values()
                ->all(),
        ];
    }
}
