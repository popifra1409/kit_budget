<?php

namespace App\Services\Planification;

use App\Models\Action;
use App\Models\Activite;
use App\Models\Exercice;
use App\Models\SousProgrammeEp;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * États isolés des actions d'un sous-programme.
 *
 * Un état par action : la tête de l'action, puis ses activités (objectif, zone, extrants,
 * indicateurs, AE/CP), puis les tâches qui portent le crédit. C'est le document que reçoit
 * un responsable de mise en œuvre pour travailler sur sa seule action.
 *
 * La descente Action -> Activités -> Tâches n'est PAS réécrite ici : elle vient de
 * CadreLogiqueEtendu, qui a été vérifié séparément (rattachement par la mère, écart
 * d'exercice neutralisé, totaux égaux à Action/Activite::getTotalAe()). Ce service ajoute
 * seulement les colonnes de pilotage de l'activité — objectif, zone d'exécution, extrants,
 * indicateurs — et découpe le résultat en un état par action.
 *
 * Deux conséquences à garder en tête :
 *
 *  - la somme des AE d'un état égale Action::getTotalAe() de son action, et la somme des
 *    états égale le total du sous-programme tel que l'imprime la matrice étendue. Un état
 *    ne peut donc pas « perdre » une tâche : si une porteuse manque ici, elle manque aussi
 *    dans la matrice, et anomalies() le dit dans les deux.
 *  - `loadMissing()` sur les activités est groupé : trois requêtes pour toute la descente,
 *    pas une par activité.
 */
class EtatsActionsSousProgramme
{
    protected CadreLogiqueEtendu $cadre;

    /** @var array<int, array>|null un seul découpage par instance */
    private ?array $etats = null;

    public function __construct(
        public readonly SousProgrammeEp $sousProgramme,
        public readonly Exercice $exercice,
    ) {
        $this->cadre = new CadreLogiqueEtendu($sousProgramme, $exercice);
    }

    public function cadre(): CadreLogiqueEtendu
    {
        return $this->cadre;
    }

    public function actions(): Collection
    {
        return $this->cadre->actions();
    }

    /**
     * Un état par action, dans l'ordre des actions du sous-programme.
     *
     * @return array<int, array{
     *     action: Action, code: ?string, titre: string, objectif: ?string, description: ?string,
     *     responsable: ?string, programme: ?string, pilote: ?string, ae: float, cp: float,
     *     activites: array<int, array{
     *         activite: Activite, code: ?string, numero: ?string, titre: ?string, statut: ?string,
     *         objectif: ?string, zone: ?string, responsable: ?string, ae: float, cp: float,
     *         extrants: array<int, array>, indicateurs: array<int, array>, lignes: array<int, array>
     *     }>,
     *     nb_activites: int, nb_lignes: int
     * }>
     */
    public function etats(): array
    {
        if ($this->etats !== null) {
            return $this->etats;
        }

        $this->chargerDetails();

        return $this->etats = array_map(function (array $bloc) {
            return [
                'action'      => $bloc['action'],
                'code'        => $bloc['action']->code,
                'titre'       => $bloc['titre'],
                'objectif'    => $bloc['objectif'],
                'description' => filled($bloc['action']->description) ? $bloc['action']->description : null,
                'responsable' => $bloc['responsable'],
                'programme'   => $bloc['programme'],
                'pilote'      => $bloc['pilote'],
                'ae'          => $bloc['ae'],
                'cp'          => $bloc['cp'],
                'activites'   => array_map(fn(array $activite) => $this->detailActivite($activite), $bloc['activites']),
                'nb_activites' => count($bloc['activites']),
                'nb_lignes'    => array_sum(array_map(fn($a) => count($a['lignes']), $bloc['activites'])),
            ];
        }, $this->cadre->blocs());
    }

    /**
     * Objectif, zone, extrants, indicateurs et responsable des activités, en trois
     * requêtes groupées pour toute la descente.
     *
     * `CadreLogiqueEtendu::activites()` rend une Collection simple : c'est une Collection
     * Eloquent qui sait charger en groupe, et elle travaille sur les modèles déjà en main.
     */
    protected function chargerDetails(): void
    {
        $activites = $this->cadre->activites()->values()->all();

        if ($activites !== []) {
            (new EloquentCollection($activites))->loadMissing(['extrants', 'indicateurs', 'responsable']);
        }
    }

    /** @param array $activite le sous-bloc produit par CadreLogiqueEtendu::sousBloc() */
    protected function detailActivite(array $activite): array
    {
        /** @var Activite $modele */
        $modele = $activite['activite'];

        return $activite + [
            'numero'  => filled($modele->numero) ? $modele->numero : null,
            'statut'  => filled($modele->statut) ? $modele->statut : null,
            'objectif' => filled($modele->objectif) ? $modele->objectif : null,
            'zone'    => filled($modele->zone_execution) ? $modele->zone_execution : null,
            'extrants' => $modele->extrants->map(fn($extrant) => [
                'libelle'   => $extrant->libelle,
                // décimaux castés en chaîne ('12.00') : rendus numériques pour l'impression
                'quantite'  => $extrant->quantite_prevue !== null ? (float) $extrant->quantite_prevue : null,
                'unite'     => $extrant->unite_mesure,
                'echeance'  => $extrant->date_realisation_prevue?->format('d/m/Y'),
            ])->all(),
            'indicateurs' => $modele->indicateurs->map(fn($indicateur) => [
                'libelle'   => $indicateur->libelle,
                'unite'     => $indicateur->unite_mesure,
                'reference' => $indicateur->valeur_reference !== null ? (float) $indicateur->valeur_reference : null,
                'cible'     => $indicateur->valeur_cible !== null ? (float) $indicateur->valeur_cible : null,
                'source'    => $indicateur->source_verification,
                'periodicite' => $indicateur->periodicite_mesure,
            ])->all(),
        ];
    }

    /** Les tâches porteuses d'une action, à plat — pour l'aperçu écran d'un état. */
    public function lignes(?string $actionCode = null): array
    {
        $lignes = [];

        foreach ($this->etats() as $etat) {
            if ($actionCode !== null && $etat['code'] !== $actionCode) {
                continue;
            }
            foreach ($etat['activites'] as $activite) {
                foreach ($activite['lignes'] as $ligne) {
                    $lignes[] = $ligne + ['activite_code' => $activite['code']];
                }
            }
        }

        return $lignes;
    }

    public function nbEtats(): int
    {
        return count($this->etats());
    }

    public function totalAe(): float
    {
        return array_sum(array_column($this->etats(), 'ae'));
    }

    public function totalCp(): float
    {
        return array_sum(array_column($this->etats(), 'cp'));
    }

    public function estVide(): bool
    {
        return $this->actions()->isEmpty();
    }

    /** @return array{reclassees: array, absentes: array} relayé de la matrice étendue */
    public function anomalies(): array
    {
        return $this->cadre->anomalies();
    }

    public function titre(): string
    {
        return $this->cadre->titre();
    }

    public static function formatMontant(float $montant): string
    {
        return CadreLogiqueEtendu::formatMontant($montant);
    }
}
