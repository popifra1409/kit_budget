<?php

namespace App\Exports;

use App\Models\OrdonnancePaiement;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class OrdonnancesSalairesExport implements
    FromArray,
    WithStyles,
    WithTitle,
    WithColumnWidths,
    WithEvents
{
    protected string  $typeOrdonnance;
    protected array   $nomenclatureIds;
    protected ?string $dateDebut;
    protected ?string $dateFin;

    // ✅ Mémoriser les numéros de lignes des en-têtes et totaux
    protected array $groupHeaderRows = [];
    protected array $groupTotalRows  = [];
    protected int   $grandTotalRow   = 0;

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
    }

    public function array(): array
    {
        // ── Charger les OP groupées par nomenclature ──────────────
        $ordonnances = OrdonnancePaiement::with([
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
            ->get()
            // ✅ Grouper par nomenclature_principale_id
            ->groupBy(
                fn($op) =>
                $op->engagement?->nomenclature_principale_id ?? 'sans_nomenclature'
            );

        $rows        = [];
        $currentRow  = 2; // Ligne 1 = en-tête du tableau
        $grandTotal  = 0;

        // ── En-tête globale ───────────────────────────────────────
        $rows[] = [
            'N° OP',
            'N° BE (Engagement)',
            'Objet',
            'Montant engagé (FCFA)',
            'Date engagement',
            'Date paiement',
            'Statut',
        ];
        $currentRow++;

        // ── Parcourir chaque groupe de nomenclature ───────────────
        foreach ($ordonnances as $nomenclatureId => $ops) {
            $nomenclature = $ops->first()?->engagement?->nomenclaturePrincipale;
            $label = $nomenclature
                ? "{$nomenclature->code} — {$nomenclature->libelle}"
                : 'Sans nomenclature';

            // ── En-tête du groupe ─────────────────────────────────
            $rows[] = ["📁 {$label}", '', '', '', '', '', ''];
            $this->groupHeaderRows[] = $currentRow;
            $currentRow++;

            $groupTotal  = 0;
            $firstDataRow = $currentRow;

            // ── Lignes du groupe ──────────────────────────────────
            foreach ($ops->sortBy('numero') as $op) {
                $eng     = $op->engagement;
                $montant = (float) ($eng?->montant_engage ?? 0);
                $groupTotal += $montant;

                $rows[] = [
                    $op->numero,
                    $eng?->numero ?? '—',
                    \Str::limit($op->objet ?? $eng?->objet ?? '—', 55),
                    $montant,
                    $eng?->date_engagement
                        ? \Carbon\Carbon::parse($eng->date_engagement)->format('d/m/Y')
                        : '—',
                    $op->date_paiement
                        ? \Carbon\Carbon::parse($op->date_paiement)->format('d/m/Y')
                        : '—',
                    ucfirst($op->statut ?? '—'),
                ];
                $currentRow++;
            }

            // ── Sous-total du groupe ──────────────────────────────
            $rows[] = [
                "Sous-total : {$label}",
                '',
                '',
                $groupTotal,
                '',
                '',
                '',
            ];
            $this->groupTotalRows[] = $currentRow;
            $currentRow++;
            $grandTotal += $groupTotal;

            // ── Ligne vide séparatrice ────────────────────────────
            $rows[]  = ['', '', '', '', '', '', ''];
            $currentRow++;
        }

        // ── GRAND TOTAL ───────────────────────────────────────────
        $rows[] = ['TOTAL GÉNÉRAL', '', '', $grandTotal, '', '', ''];
        $this->grandTotalRow = $currentRow;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // En-tête des colonnes (ligne 1)
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E79'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,  // N° OP
            'B' => 22,  // N° BE
            'C' => 50,  // Objet
            'D' => 22,  // Montant
            'E' => 18,  // Date engagement
            'F' => 18,  // Date paiement
            'G' => 14,  // Statut
        ];
    }

    public function title(): string
    {
        return $this->typeOrdonnance === 'standard'
            ? 'OP Standard par nomenclature'
            : 'OPT Impôt par nomenclature';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ── Styles en-têtes de groupe ─────────────────────
                foreach ($this->groupHeaderRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '1F4E79']],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D6E4F0'],
                        ],
                        'borders' => [
                            'bottom' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color'       => ['rgb' => '1F4E79'],
                            ],
                        ],
                    ]);
                    // Fusionner les cellules de l'en-tête groupe
                    $sheet->mergeCells("A{$row}:G{$row}");
                }

                // ── Styles sous-totaux de groupe ──────────────────
                foreach ($this->groupTotalRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'italic' => true],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'EBF5FB'],
                        ],
                        'borders' => [
                            'top' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => '1F4E79'],
                            ],
                        ],
                    ]);
                    // Fusionner libellé sous-total
                    $sheet->mergeCells("A{$row}:C{$row}");
                    // Format nombre pour la colonne D
                    $sheet->getStyle("D{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // ── Style grand total ─────────────────────────────
                if ($this->grandTotalRow > 0) {
                    $row = $this->grandTotalRow;
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '1F4E79'],
                        ],
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color'       => ['rgb' => '1F4E79'],
                            ],
                        ],
                    ]);
                    $sheet->mergeCells("A{$row}:C{$row}");
                    $sheet->getStyle("D{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // ── Format nombre pour toutes les lignes données ──
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle("D2:D{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // ── Bordures légères sur les données ─────────────
                $sheet->getStyle("A1:G{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'D0D0D0'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
