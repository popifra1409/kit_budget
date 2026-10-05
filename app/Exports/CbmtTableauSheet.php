<?php

namespace App\Exports;

use App\Models\CbmtExercice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Prévision à moyen terme par titres — détail des lignes sous chaque titre.
 * Même source que l'écran et le PDF : CbmtExercice::syntheseParTitres().
 * Reçoit directement le CBMT : utilisable avec ou sans CDMT.
 */
class CbmtTableauSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    /** Lignes de titre / total (en gras), repérées pendant la construction. */
    protected array $lignesEnGras = [];

    public function __construct(protected CbmtExercice $cbmt, protected string $nature) {}

    protected function colonnes(): array
    {
        $n = $this->cbmt->anneeReference();
        $depense = $this->nature === 'depense';

        return array_filter([
            'prevision_n_initiale'     => "Prévision {$n} initiale",
            'montant_n'                => "Prévision {$n} actualisée",
            'realisation_n'            => $depense ? "Réalisation {$n} engagé" : "Réalisation {$n} recouvré",
            'realisation_n_ordonnance' => $depense ? "Réalisation {$n} ordonnancé" : null,
            'montant_n_plus_1'         => (string) ($n + 1),
            'montant_n_plus_2'         => (string) ($n + 2),
            'montant_n_plus_3'         => (string) ($n + 3),
        ]);
    }

    public function headings(): array
    {
        return array_merge(['Compte', 'Libellé', 'LR/MN'], array_values($this->colonnes()));
    }

    public function array(): array
    {
        $colonnes = array_keys($this->colonnes());
        $groupes = $this->cbmt->syntheseParTitres($this->nature);
        $lignes = [];
        $rang = 2; // ligne 1 = en-têtes

        foreach ($groupes as $g) {
            $lignes[] = array_merge(['', $g['libelle'], ''], array_map(fn($c) => $g['totaux'][$c], $colonnes));
            $this->lignesEnGras[] = $rang++;

            foreach ($g['lignes'] as $l) {
                $lignes[] = array_merge([(string) $l->code, $l->libelle, $l->type_ligne], array_map(fn($c) => (float) $l->{$c}, $colonnes));
                $rang++;
            }
        }

        $lignes[] = array_merge(
            ['', $this->nature === 'depense' ? 'TOTAL DÉPENSES' : 'TOTAL RESSOURCES', ''],
            array_map(fn($c) => $groupes->sum(fn($g) => $g['totaux'][$c]), $colonnes)
        );
        $this->lignesEnGras[] = $rang;

        return $lignes;
    }

    public function styles(Worksheet $sheet): array
    {
        // Montants au format « 1 234 567 »
        $derniereColonne = chr(ord('A') + count($this->headings()) - 1);
        $sheet->getStyle("D2:{$derniereColonne}" . ($sheet->getHighestRow()))
            ->getNumberFormat()->setFormatCode('#,##0');

        $styles = [1 => ['font' => ['bold' => true]]];
        foreach ($this->lignesEnGras as $ligne) {
            $styles[$ligne] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    public function title(): string
    {
        // Nom d'onglet Excel : 31 caractères maximum
        return $this->nature === 'ressource' ? 'Ressources par titres' : 'Dépenses par titres';
    }
}
