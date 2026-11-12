<?php

namespace App\Exports\EtatsActions;

use App\Models\Exercice;
use App\Models\SousProgrammeEp;
use App\Services\Planification\CadreLogiqueEtendu;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Onglet = un état isolé d'action. Le contexte est répété sur chaque ligne pour qu'un tri
 * ou un filtre reste juste, les montants sortent en nombres, et les neuf colonnes de la
 * tâche viennent de CadreLogiqueEtendu::COLONNES — les mêmes que le PDF.
 */
class EtatActionSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(
        protected string $titre,
        protected array $etat,
        protected string $chapeau,
        protected Exercice $exercice,
        protected SousProgrammeEp $sousProgramme,
    ) {}

    public function title(): string
    {
        return $this->titre;
    }

    public function headings(): array
    {
        return array_merge(
            $this->entetesContexte(),
            array_values(CadreLogiqueEtendu::COLONNES),
            ['Extrants attendus', 'Indicateurs (référence -> cible)', 'Note de rattachement'],
        );
    }

    public function collection()
    {
        return collect($this->lignes());
    }

    /** @return array<int, array> lignes du classeur, total de l'action en dernière ligne */
    public function lignes(): array
    {
        $tete = [
            $this->chapeau,
            $this->exercice->annee,
            $this->etat['titre'],
            $this->etat['objectif'],
            $this->etat['responsable'],
            $this->etat['programme'],
            $this->etat['pilote'] ?? 'agent non désigné',
        ];

        $lignes = [];

        if ($this->etat['activites'] === []) {
            $lignes[] = $this->rangee(
                array_merge($tete, ['Aucune activité rattachée à cette action.', null, null, null, null]),
                $this->blancsTaches(),
                [null, null, "AE de l'action : {$this->etat['ae']} — CP : {$this->etat['cp']}"],
            );
        }

        foreach ($this->etat['activites'] as $activite) {
            $contexte = array_merge($tete, [
                trim($activite['code'].' — '.$activite['titre']),
                $activite['numero'],
                $activite['statut'],
                $activite['responsable'],
                $activite['zone'],
            ]);
            $details = [$this->extrants($activite), $this->indicateurs($activite)];

            if ($activite['lignes'] === []) {
                $lignes[] = $this->rangee(
                    $contexte,
                    $this->blancsTaches(),
                    array_merge($details, ["Aucune tâche porteuse de crédit — AE : {$activite['ae']}, CP : {$activite['cp']}"]),
                );
                continue;
            }

            foreach ($activite['lignes'] as $ligne) {
                $lignes[] = $this->rangee($contexte, $this->tache($ligne), array_merge($details, [$this->note($ligne)]));
            }
        }

        // Le total de l'action se pose dans les colonnes AE/CP, jamais dans une colonne de texte.
        $total = array_fill(0, count($this->headings()), null);
        $total[0] = 'TOTAL '.$this->etat['titre'];
        $iAe = array_search('AE (FCFA)', $this->headings(), true);
        $total[$iAe] = $this->etat['ae'];
        $total[$iAe + 1] = $this->etat['cp'];
        $lignes[] = $total;

        return $lignes;
    }

    /** Assemblage unique : contexte + colonnes de la tâche + détails, à la largeur des en-têtes. */
    protected function rangee(array $contexte, array $tache, array $details): array
    {
        return array_merge($contexte, $tache, $details);
    }

    /** @return string[] les en-têtes qui précèdent les colonnes de la tâche */
    protected function entetesContexte(): array
    {
        return [
            'Sous-programme',
            'Exercice',
            'Action',
            "Objectif de l'action",
            'Responsable de l\'action',
            'Programme porteur',
            'Agent responsable du programme',
            'Activité',
            'Numéro d\'activité',
            'Statut de l\'activité',
            'Responsable de l\'activité',
            'Zone d\'exécution',
        ];
    }

    /** @return array<int, mixed> les neuf colonnes d'une tâche porteuse */
    protected function tache(array $ligne): array
    {
        return [
            implode(' > ', $ligne['designation']),
            $ligne['nomenclature'],
            $ligne['ae'],
            $ligne['cp'],
            $ligne['delai'],
            $ligne['guichet'],
            $ligne['service'],
            $ligne['indicateur'],
            $ligne['resultat'],
        ];
    }

    /** @return array<int, null> les neuf colonnes de la tâche mises à blanc */
    protected function blancsTaches(): array
    {
        return array_fill(0, count(CadreLogiqueEtendu::COLONNES), null);
    }

    protected function extrants(array $activite): ?string
    {
        if ($activite['extrants'] === []) {
            return null;
        }

        return implode(' ; ', array_map(function (array $extrant) {
            $unite = $extrant['unite'] !== null ? ' '.$extrant['unite'] : '';
            $quantite = $extrant['quantite'] !== null ? $extrant['quantite'].$unite : ($unite ?: '—');
            $echeance = $extrant['echeance'] !== null ? ' (prévu '.$extrant['echeance'].')' : '';

            return $extrant['libelle'].' : '.$quantite.$echeance;
        }, $activite['extrants']));
    }

    protected function indicateurs(array $activite): ?string
    {
        if ($activite['indicateurs'] === []) {
            return null;
        }

        return implode(' ; ', array_map(fn(array $i) => sprintf(
            '%s%s : %s -> %s',
            $i['libelle'],
            $i['unite'] !== null ? ' ('.$i['unite'].')' : '',
            $i['reference'] ?? '—',
            $i['cible'] ?? '—'
        ), $activite['indicateurs']));
    }

    /** Signal de reclassement, identique à celui de la matrice étendue. */
    protected function note(array $ligne): ?string
    {
        $mere = $ligne['mere'];

        if ($mere === null || (int) $ligne['tache']->activite_id === (int) $mere->activite_id) {
            return null;
        }

        return "la tâche se déclare dans l'activité {$ligne['tache']->activite_id} en saisie";
    }
}
