<?php

namespace App\Exports;

use App\Services\BudgetProgrammeService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;

class BudgetProgrammeRecettesSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    private array $donnees;
    private int   $anneeRef;

    public function __construct(
        int    $anneeRef,
        private string $titre     = 'Budget Programme',
        private string $structure = 'CHUY'
    ) {
        $this->anneeRef = $anneeRef;
        $service        = new BudgetProgrammeService();
        $this->donnees  = $service->collecterDonnees($anneeRef);
    }

    public function title(): string
    {
        return 'RECETTES';
    }

    public function array(): array
    {
        $d       = $this->donnees;
        $annees  = $d['annees'];
        $recettes = $d['recettes'] ?? ['lignes' => [], 'total_general' => []];
        $rows    = [];

        // ── TITRE ────────────────────────────────────────────
        $rows[] = [$this->structure . ' — ' . $this->titre . ' — RECETTES'];
        $rows[] = ['Budget Programme ' . ($annees['n_2']) . '-' . ($annees['n2'])];
        $rows[] = [];

        // ── EN-TÊTE COLONNES ─────────────────────────────────
        $rows[] = [
            'Imputation',
            'Rubrique / Désignation',
            'Prévisions ' . $annees['n_2'],
            'Réalisations ' . $annees['n_2'],
            '%',
            'Prévisions ' . $annees['n_1'],
            'Réalisations ' . $annees['n_1'],
            '%',
            'Prévisions ' . $annees['n'],
            'Réalisations ' . $annees['n'],
            '%',
            'Prévisions ' . $annees['n1'],
            'Prévisions ' . $annees['n2'],
            'TOTAL ' . $annees['n1'] . '-' . $annees['n2'],
            'TOTAL ' . $annees['n'] . '-' . $annees['n1'] . '-' . $annees['n2'],
        ];

        $currentGroupe  = null;
        $currentChapter = null;
        $sousTotal      = null;
        $champsSousTotal = [
            'prev_n_2',
            'real_n_2',
            'prev_n_1',
            'real_n_1',
            'prev_n',
            'real_n',
            'prev_n1',
            'prev_n2',
            'total_n1_n2',
            'total_n_n1_n2',
        ];

        // ── LIGNES DE DONNÉES (groupées par groupe de nomenclature) ─
        foreach ($recettes['lignes'] as $ligne) {
            $chapter       = substr($ligne['imputation'], 0, 3);
            $isArticle     = strlen($ligne['imputation']) > 3;
            $groupeLibelle = $ligne['groupe_libelle'] ?? 'Non classées / Hors groupe';

            if ($isArticle && $groupeLibelle !== $currentGroupe) {
                if ($sousTotal !== null) {
                    $rows[] = $this->ligneSousTotal('SOUS-TOTAL — ' . $currentGroupe, $sousTotal);
                }
                $currentGroupe  = $groupeLibelle;
                $currentChapter = null;
                $sousTotal      = array_fill_keys($champsSousTotal, 0);

                $rows[] = [($ligne['groupe_id'] ? '' : '⚠️'), strtoupper($currentGroupe)];
            }

            if ($chapter !== $currentChapter && $isArticle) {
                $nom = \App\Models\NomenclatureBudgetaire::where('code', $chapter)->value('libelle')
                    ?? "Chapitre {$chapter}";
                $rows[] = [$chapter, strtoupper($nom)];
                $currentChapter = $chapter;
            }

            $rows[] = [
                $ligne['imputation'],
                $ligne['rubrique'],
                $ligne['prev_n_2']  ?? 0,
                $ligne['real_n_2']  ?? 0,
                $ligne['taux_n_2']  ? number_format($ligne['taux_n_2'] * 100, 2) . '%' : '—',
                $ligne['prev_n_1']  ?? 0,
                $ligne['real_n_1']  ?? 0,
                $ligne['taux_n_1']  ? number_format($ligne['taux_n_1'] * 100, 2) . '%' : '—',
                $ligne['prev_n']    ?? 0,
                $ligne['real_n']    ?? 0,
                $ligne['taux_n']    ? number_format($ligne['taux_n'] * 100, 2) . '%' : '—',
                $ligne['prev_n1']   ?? 0,
                $ligne['prev_n2']   ?? 0,
                $ligne['total_n1_n2']   ?? 0,
                $ligne['total_n_n1_n2'] ?? 0,
            ];

            if ($sousTotal !== null) {
                foreach ($champsSousTotal as $champ) {
                    $sousTotal[$champ] += (float) ($ligne[$champ] ?? 0);
                }
            }
        }

        if ($sousTotal !== null) {
            $rows[] = $this->ligneSousTotal('SOUS-TOTAL — ' . $currentGroupe, $sousTotal);
        }

        // ── LIGNE TOTAL GÉNÉRAL ───────────────────────────────
        $rows[] = [];
        $tg = $recettes['total_general'] ?? [];
        $rows[] = [
            '',
            'TOTAL GÉNÉRAL DES RECETTES',
            $tg['prev_n_2']  ?? 0,
            $tg['real_n_2']  ?? 0,
            '',
            $tg['prev_n_1']  ?? 0,
            $tg['real_n_1']  ?? 0,
            '',
            $tg['prev_n']    ?? 0,
            $tg['real_n']    ?? 0,
            '',
            $tg['prev_n1']   ?? 0,
            $tg['prev_n2']   ?? 0,
            $tg['total_n1_n2']   ?? 0,
            $tg['total_n_n1_n2'] ?? 0,
        ];

        return $rows;
    }

    private function ligneSousTotal(string $libelle, array $sousTotal): array
    {
        return [
            '',
            $libelle,
            $sousTotal['prev_n_2']  ?? 0,
            $sousTotal['real_n_2']  ?? 0,
            '',
            $sousTotal['prev_n_1']  ?? 0,
            $sousTotal['real_n_1']  ?? 0,
            '',
            $sousTotal['prev_n']    ?? 0,
            $sousTotal['real_n']    ?? 0,
            '',
            $sousTotal['prev_n1']   ?? 0,
            $sousTotal['prev_n2']   ?? 0,
            $sousTotal['total_n1_n2']   ?? 0,
            $sousTotal['total_n_n1_n2'] ?? 0,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 52,
            'C' => 18,
            'D' => 18,
            'E' => 8,
            'F' => 18,
            'G' => 18,
            'H' => 8,
            'I' => 18,
            'J' => 18,
            'K' => 8,
            'L' => 18,
            'M' => 18,
            'N' => 22,
            'O' => 25,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '166534']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            4 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '166534']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $numFormat = '#,##0';

                $numCols = ['C', 'D', 'F', 'G', 'I', 'J', 'L', 'M', 'N', 'O'];
                foreach ($numCols as $col) {
                    $sheet->getStyle("{$col}5:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode($numFormat);
                }

                $sheet->mergeCells('A1:O1');
                $sheet->mergeCells('A2:O2');

                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(4)->setRowHeight(35);

                $sheet->freezePane('C5');

                $sheet->getStyle("A{$lastRow}:O{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '166534']],
                ]);

                $sheet->getStyle("A4:O{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);

                $sheet->getStyle("L5:M{$lastRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFDE7']],
                ]);

                $sheet->getStyle("B5:B{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("A1:O{$lastRow}")
                    ->getFont()->setName('Arial')->setSize(9);
            },
        ];
    }
}
