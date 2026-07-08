<?php

namespace App\Exports;

use App\Models\PrevisionRecette;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PrevisionRecetteExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    ShouldAutoSize
{
    protected $previsionRecette;

    public function __construct(PrevisionRecette $previsionRecette)
    {
        // Charger la relation exerciceBudgetaire au lieu de exercice
        $this->previsionRecette = $previsionRecette->load('exerciceBudgetaire');
    }

    public function collection()
    {
        return $this->previsionRecette
            ->lignesPrevisions()
            ->with('nomenclature')
            ->orderBy('ordre')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Code',
            'Libellé',
            'Montant Initial',
            'Montant Rectifié',
            'Montant Recouvré',
            'Écart',
            'Taux (%)',
            'Observations',
        ];
    }

    public function map($ligne): array
    {
        return [
            $ligne->code_nomenclature,
            $ligne->libelle_nomenclature,
            number_format($ligne->montant_prevu_initial, 0, ',', ' '),
            number_format($ligne->montant_rectifie, 0, ',', ' '),
            number_format($ligne->montant_recouvre, 0, ',', ' '),
            number_format($ligne->ecart, 0, ',', ' '),
            number_format($ligne->taux_recouvrement, 2, ',', ' '),
            $ligne->observations ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Titre principal
        $sheet->insertNewRowBefore(1, 3);
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'PRÉVISION DE RECETTES');

        $sheet->mergeCells('A2:H2');
        $annee = $this->previsionRecette->exerciceBudgetaire ? $this->previsionRecette->exerciceBudgetaire->annee : 'N/A';
        $sheet->setCellValue('A2', $this->previsionRecette->libelle . ' - Exercice ' . $annee);

        // Style du titre
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '1F4788'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A2:H2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Style des en-têtes
        $sheet->getStyle('A4:H4')->applyFromArray([
            'font' => [
                'bold' => true,
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
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Hauteur des lignes
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(4)->setRowHeight(25);

        // Bordures pour toutes les cellules de données
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A4:H' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        // Alignement des colonnes numériques
        $sheet->getStyle('C5:G' . $lastRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Ligne de total
        $totalRow = $lastRow + 1;
        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $sheet->mergeCells('A' . $totalRow . ':B' . $totalRow);

        $sheet->setCellValue('C' . $totalRow, number_format($this->previsionRecette->getTotalPrevuInitial(), 0, ',', ' '));
        $sheet->setCellValue('D' . $totalRow, number_format($this->previsionRecette->getTotalPrevuRectifie(), 0, ',', ' '));
        $sheet->setCellValue('E' . $totalRow, number_format($this->previsionRecette->getTotalRecouvre(), 0, ',', ' '));
        $sheet->setCellValue('F' . $totalRow, number_format($this->previsionRecette->getEcartGlobal(), 0, ',', ' '));
        $sheet->setCellValue('G' . $totalRow, number_format($this->previsionRecette->getTauxRecouvrement(), 2, ',', ' ') . ' %');

        $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                ],
            ],
        ]);

        return [];
    }

    public function title(): string
    {
        return 'Prévisions Recettes';
    }
}
