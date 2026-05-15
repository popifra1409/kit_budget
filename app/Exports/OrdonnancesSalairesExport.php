<?php
// app/Exports/OrdonnancesSalairesExport.php

namespace App\Exports;

use App\Models\OrdonnancePaiement;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class OrdonnancesSalairesExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    WithColumnWidths,
    WithEvents
{
    protected string $typeOrdonnance;
    protected array  $nomenclatureIds;
    protected ?string $dateDebut;
    protected ?string $dateFin;
    protected string  $titre;

    public function __construct(
        string  $typeOrdonnance,
        array   $nomenclatureIds,
        ?string $dateDebut = null,
        ?string $dateFin   = null
    ) {
        $this->typeOrdonnance  = $typeOrdonnance;
        $this->nomenclatureIds = $nomenclatureIds;
        $this->dateDebut       = $dateDebut;
        $this->dateFin         = $dateFin;
        $this->titre           = $typeOrdonnance === 'standard'
            ? 'OP Standard - Salaires'
            : 'OPT Impôt - Salaires';
    }

    public function collection()
    {
        return OrdonnancePaiement::with([
            'engagement.nomenclaturePrincipale',
            'engagement.engageable',
        ])
            ->where('type_ordonnance', $this->typeOrdonnance)
            ->whereHas('engagement', function ($q) {
                $q->whereIn('nomenclature_principale_id', $this->nomenclatureIds);
            })
            ->when(
                $this->dateDebut,
                fn($q) =>
                $q->whereDate('date_emission', '>=', $this->dateDebut)
            )
            ->when(
                $this->dateFin,
                fn($q) =>
                $q->whereDate('date_emission', '<=', $this->dateFin)
            )
            ->orderBy('date_emission')
            ->orderBy('numero')
            ->get();
    }

    public function headings(): array
    {
        return [
            'N° OP',
            'N° BE (Engagement)',
            'Nomenclature',
            'Objet',
            'Montant engagé (FCFA)',
            'Date engagement',
            'Date paiement',
            'Statut',
        ];
    }

    public function map($op): array
    {
        $engagement = $op->engagement;
        $nomenclature = $engagement?->nomenclaturePrincipale;

        return [
            $op->numero,
            $engagement?->numero ?? '—',
            ($nomenclature ? "{$nomenclature->code} - {$nomenclature->libelle}" : '—'),
            $op->objet ?? $engagement?->objet ?? '—',
            number_format((float) ($engagement?->montant_engage ?? 0), 0, ',', ' '),
            $engagement?->date_engagement
                ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y')
                : '—',
            $op->date_paiement
                ? \Carbon\Carbon::parse($op->date_paiement)->format('d/m/Y')
                : '—',
            ucfirst($op->statut ?? '—'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // En-tête
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // N° OP
            'B' => 22,  // N° BE
            'C' => 35,  // Nomenclature
            'D' => 45,  // Objet
            'E' => 22,  // Montant engagé
            'F' => 18,  // Date engagement
            'G' => 18,  // Date paiement
            'H' => 14,  // Statut
        ];
    }

    public function title(): string
    {
        return $this->titre;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $lastRow    = $sheet->getHighestRow();
                $totalRow   = $lastRow + 2;

                // ── Ligne de total ────────────────────────────────────
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->setCellValue(
                    "E{$totalRow}",
                    "=SUM(E2:E{$lastRow})"
                );

                $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2'],
                    ],
                ]);

                // ── Bordures sur toutes les données ───────────────────
                $sheet->getStyle("A1:H{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => 'BFBFBF'],
                        ],
                    ],
                ]);

                // ── Colonne montant : format nombre ───────────────────
                $sheet->getStyle("E2:E{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                $sheet->getStyle("E{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            },
        ];
    }
}
