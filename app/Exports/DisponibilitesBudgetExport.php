<?php

namespace App\Exports;

use App\Models\Budget;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DisponibilitesBudgetExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    WithColumnWidths,
    WithEvents
{
    protected Budget $budget;
    protected int $rowNumber = 0;

    public function __construct(Budget $budget)
    {
        $this->budget = $budget;
    }

    /**
     * Collection des lignes budgétaires avec nomenclature
     */
    public function collection()
    {
        return $this->budget->lignesBudgetaires()
            ->with(['nomenclature'])
            ->get()
            ->sortBy(function ($ligne) {
                return $ligne->nomenclature?->code ?? 'ZZZ';
            });
    }

    /**
     * En-têtes des colonnes
     */
    public function headings(): array
    {
        return [
            'Code',
            'Nomenclature',
            'Chapitre',
            'Article',
            'Paragraphe',
            'Budget Initial',
            'Vir. Entrants',
            'Vir. Sortants',
            'Budget Rectifié',
            'Engagements',
            'Ordonnancés',
            'Liquidés',
            'Payés',
            'Disponible Eng.',
            'Disponible Ord.',
            'Taux Engagement (%)',
            'Taux Exécution (%)',
        ];
    }

    /**
     * Mapping des données
     */
    public function map($ligne): array
    {
        $this->rowNumber++;

        $budgetInitial = $ligne->budget_initial ?? 0;
        $virementsEntrants = $ligne->virements_entrants ?? 0;
        $virementsSortants = $ligne->virements_sortants ?? 0;
        $budgetRectifie = $ligne->budget_rectifie ?? ($budgetInitial + $virementsEntrants - $virementsSortants);

        $engage = $ligne->engage ?? 0;
        $ordonne = $ligne->ordonne ?? 0;
        $liquide = $ligne->liquide ?? 0;
        $paye = $ligne->paye ?? 0;

        $disponibleEngagement = $ligne->disponible_engagement ?? ($budgetRectifie - $engage);
        $disponibleOrdonnancement = $ligne->disponible_ordonnancement ?? ($budgetRectifie - $ordonne);

        $tauxEngagement = $budgetRectifie > 0 ? ($engage / $budgetRectifie) * 100 : 0;
        $tauxExecution = $budgetRectifie > 0 ? ($paye / $budgetRectifie) * 100 : 0;

        return [
            $ligne->nomenclature?->code ?? '',
            $ligne->nomenclature?->libelle ?? '',
            $ligne->nomenclature?->chapitre ?? '',
            $ligne->nomenclature?->article ?? '',
            $ligne->nomenclature?->paragraphe ?? '',
            $budgetInitial,
            $virementsEntrants,
            $virementsSortants,
            $budgetRectifie,
            $engage,
            $ordonne,
            $liquide,
            $paye,
            $disponibleEngagement,
            $disponibleOrdonnancement,
            round($tauxEngagement, 2),
            round($tauxExecution, 2),
        ];
    }

    /**
     * Styles des cellules
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style de l'en-tête
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Titre de la feuille
     */
    public function title(): string
    {
        return 'Disponibilités';
    }

    /**
     * Largeur des colonnes
     */
    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Code
            'B' => 35,  // Nomenclature
            'C' => 10,  // Chapitre
            'D' => 10,  // Article
            'E' => 10,  // Paragraphe
            'F' => 16,  // Budget Initial
            'G' => 14,  // Vir. Entrants
            'H' => 14,  // Vir. Sortants
            'I' => 16,  // Budget Rectifié
            'J' => 15,  // Engagements
            'K' => 15,  // Ordonnancés
            'L' => 15,  // Liquidés
            'M' => 15,  // Payés
            'N' => 16,  // Dispo. Eng.
            'O' => 16,  // Dispo. Ord.
            'P' => 13,  // Taux Eng.
            'Q' => 13,  // Taux Exec.
        ];
    }

    /**
     * Événements après la création de la feuille
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Ajouter une ligne de titre
                $sheet->insertNewRowBefore(1, 2);

                // Titre principal
                $sheet->setCellValue('A1', 'ÉTAT DES DISPONIBILITÉS BUDGÉTAIRES');
                $sheet->mergeCells('A1:' . $highestColumn . '1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // Sous-titre
                $sheet->setCellValue(
                    'A2',
                    "Budget: {$this->budget->libelle} - Exercice: {$this->budget->exercice} - " .
                        "Généré le: " . now()->format('d/m/Y à H:i')
                );
                $sheet->mergeCells('A2:' . $highestColumn . '2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 10, 'italic' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Bordures pour toutes les cellules de données
                $sheet->getStyle('A3:' . $highestColumn . ($highestRow + 2))
                    ->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);

                // Format monétaire pour les colonnes de montants (F à O)
                $sheet->getStyle('F4:O' . ($highestRow + 2))
                    ->getNumberFormat()
                    ->setFormatCode('#,##0 "FCFA"');

                // Format pourcentage pour les colonnes P et Q
                $sheet->getStyle('P4:Q' . ($highestRow + 2))
                    ->getNumberFormat()
                    ->setFormatCode('0.00"%"');

                // Alignement centré pour les codes
                $sheet->getStyle('A4:E' . ($highestRow + 2))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('P4:Q' . ($highestRow + 2))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Alignement à droite pour les montants
                $sheet->getStyle('F4:O' . ($highestRow + 2))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Ligne de totaux
                $totalRow = $highestRow + 3;
                $sheet->setCellValue('A' . $totalRow, 'TOTAL GÉNÉRAL');
                $sheet->mergeCells('A' . $totalRow . ':E' . $totalRow);

                // Formules de totaux
                $sheet->setCellValue('F' . $totalRow, '=SUM(F4:F' . ($highestRow + 2) . ')');
                $sheet->setCellValue('G' . $totalRow, '=SUM(G4:G' . ($highestRow + 2) . ')');
                $sheet->setCellValue('H' . $totalRow, '=SUM(H4:H' . ($highestRow + 2) . ')');
                $sheet->setCellValue('I' . $totalRow, '=SUM(I4:I' . ($highestRow + 2) . ')');
                $sheet->setCellValue('J' . $totalRow, '=SUM(J4:J' . ($highestRow + 2) . ')');
                $sheet->setCellValue('K' . $totalRow, '=SUM(K4:K' . ($highestRow + 2) . ')');
                $sheet->setCellValue('L' . $totalRow, '=SUM(L4:L' . ($highestRow + 2) . ')');
                $sheet->setCellValue('M' . $totalRow, '=SUM(M4:M' . ($highestRow + 2) . ')');
                $sheet->setCellValue('N' . $totalRow, '=SUM(N4:N' . ($highestRow + 2) . ')');
                $sheet->setCellValue('O' . $totalRow, '=SUM(O4:O' . ($highestRow + 2) . ')');

                // Taux globaux
                $sheet->setCellValue('P' . $totalRow, '=IF(I' . $totalRow . '>0, J' . $totalRow . '/I' . $totalRow . '*100, 0)');
                $sheet->setCellValue('Q' . $totalRow, '=IF(I' . $totalRow . '>0, M' . $totalRow . '/I' . $totalRow . '*100, 0)');

                // Style de la ligne de totaux
                $sheet->getStyle('A' . $totalRow . ':' . $highestColumn . $totalRow)
                    ->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E7E6E6'],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);

                // Mise en forme conditionnelle pour les disponibilités négatives
                for ($row = 4; $row <= $highestRow + 2; $row++) {
                    $disponibleEng = $sheet->getCell('N' . $row)->getValue();
                    $disponibleOrd = $sheet->getCell('O' . $row)->getValue();

                    if ($disponibleEng < 0) {
                        $sheet->getStyle('N' . $row)->applyFromArray([
                            'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFC7CE'],
                            ],
                        ]);
                    }

                    if ($disponibleOrd < 0) {
                        $sheet->getStyle('O' . $row)->applyFromArray([
                            'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFC7CE'],
                            ],
                        ]);
                    }
                }

                // Figer les volets
                $sheet->freezePane('A4');

                // Ajuster hauteur des lignes
                for ($row = 4; $row <= $highestRow + 2; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(18);
                }
            },
        ];
    }
}
