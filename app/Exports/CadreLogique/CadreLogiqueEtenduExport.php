<?php

namespace App\Exports\CadreLogique;

use App\Services\Planification\CadreLogiqueEtendu;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Feuille d'analyse d'un sous-programme : le contexte (programme, pilote, action, activité)
 * est répété sur chaque ligne pour qu'un tri ou un tableau croisé reste juste, et les
 * montants sortent en nombres — pas en chaînes « 1 500 000 FCFA » — pour qu'Excel les somme.
 * Les neuf colonnes d'en-tête viennent de CadreLogiqueEtendu::COLONNES, comme le PDF.
 */
class CadreLogiqueEtenduExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(protected CadreLogiqueEtendu $rapport) {}

    public function title(): string
    {
        $titre = preg_replace(
            '/[\[\]:*?\/\\\\]/',
            '-',
            "{$this->rapport->sousProgramme->code} {$this->rapport->sousProgramme->libelle}"
        );

        return trim(mb_substr($titre, 0, 31));
    }

    public function headings(): array
    {
        return array_merge($this->entetesContexte(), array_values(CadreLogiqueEtendu::COLONNES), [
            'Note de rattachement',
        ]);
    }

    public function collection()
    {
        $colonnesTache = count(CadreLogiqueEtendu::COLONNES);
        $lignes = [];

        foreach ($this->rapport->blocs() as $bloc) {
            $contexte = [
                $this->rapport->titre(),
                "exercice {$this->rapport->exercice->annee}",
                $bloc['programme'],
                $bloc['pilote'] ?? 'agent non désigné',
                $bloc['titre'],
                $bloc['objectif'],
                $bloc['responsable'],
            ];

            if ($bloc['activites'] === []) {
                $lignes[] = array_merge($contexte, [
                    'Aucune activité rattachée à cette action.',
                    null,
                ], $this->blancs(), [
                    "AE de l'action : {$bloc['ae']} — CP : {$bloc['cp']}",
                ]);
                continue;
            }

            foreach ($bloc['activites'] as $activite) {
                $contexteActivite = array_merge($contexte, [
                    trim("{$activite['code']} — {$activite['titre']}"),
                    $activite['responsable'],
                ]);

                if ($activite['lignes'] === []) {
                    $lignes[] = array_merge($contexteActivite, $this->blancs(), [
                        "Aucune tâche porteuse de crédit — AE : {$activite['ae']}, CP : {$activite['cp']}",
                    ]);
                    continue;
                }

                foreach ($activite['lignes'] as $ligne) {
                    $lignes[] = array_merge($contexteActivite, [
                        implode(' > ', $ligne['designation']),
                        $ligne['nomenclature'],
                        $ligne['ae'],
                        $ligne['cp'],
                        $ligne['delai'],
                        $ligne['guichet'],
                        $ligne['service'],
                        $ligne['indicateur'],
                        $ligne['resultat'],
                        $this->note($ligne),
                    ]);
                }
            }
        }

        // Le total se pose dans les colonnes AE/CP, jamais dans une colonne de texte :
        // une somme noyée dans une cellule décalée se relit mal dans un tableau croisé.
        $total = array_fill(0, count($this->entetesContexte()) + $colonnesTache + 1, null);
        $total[0] = 'TOTAL du sous-programme';
        $total[count($this->entetesContexte()) + 2] = $this->rapport->totalAe();
        $total[count($this->entetesContexte()) + 3] = $this->rapport->totalCp();

        $lignes[] = $total;

        return collect($lignes);
    }

    /** Les en-têtes qui précèdent les colonnes CadreLogiqueEtendu::COLONNES. */
    protected function entetesContexte(): array
    {
        return [
            'Sous-programme',
            'Exercice',
            'Programme porteur',
            'Agent responsable du programme',
            'Action',
            "Objectif de l'action",
            'Responsable de l\'action',
            'Activité',
            "Responsable de l'activité",
        ];
    }

    /** Les neuf colonnes de la tâche mises à blanc (activité ou action sans ligne). */
    protected function blancs(): array
    {
        return array_fill(0, count(CadreLogiqueEtendu::COLONNES), null);
    }

    /**
     * Signale la sous-tâche dont le `activite_id` contredit celui de sa mère : l'état la
     * compte dans l'activité de la mère, la fiche de saisie dans la sienne.
     */
    protected function note(array $ligne): ?string
    {
        $mere = $ligne['mere'];

        if ($mere === null || (int) $ligne['tache']->activite_id === (int) $mere->activite_id) {
            return null;
        }

        return "la tâche se déclare dans l'activité {$ligne['tache']->activite_id} en saisie";
    }
}
