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
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;

class BudgetProgrammeDepensesSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
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
        $this->donnees  = $service->collecterDonnees($anneeRef, 'fonctionnement');
    }

    public function title(): string
    {
        return 'DEPENSES';
    }

    public function array(): array
    {
        $d      = $this->donnees;
        $annees = $d['annees'];
        $rows   = [];

        // ── TITRE ────────────────────────────────────────────
        $rows[] = [$this->structure . ' — ' . $this->titre . ' — DÉPENSES DE FONCTIONNEMENT'];
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

        $currentChapter = null;

        // ── LIGNES DE DONNÉES ─────────────────────────────────
        foreach ($d['lignes'] as $ligne) {
            $chapter = substr($ligne['imputation'], 0, 3);

            // Insérer en-tête de chapitre si changement
            if ($chapter !== $currentChapter && strlen($ligne['imputation']) > 3) {
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
        }

        // ── LIGNE TOTAL GÉNÉRAL ───────────────────────────────
        $rows[] = [];
        $tg = $d['total_general'];
        $rows[] = [
            '',
            'TOTAL GÉNÉRAL DES DÉPENSES DE FONCTIONNEMENT',
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

    public function columnWidths(): array
    {
        return [
            'A' => 14,   // Imputation
            'B' => 52,   // Rubrique
            'C' => 18,   // Prévis N-2
            'D' => 18,   // Réalis N-2
            'E' => 8,    // %
            'F' => 18,   // Prévis N-1
            'G' => 18,   // Réalis N-1
            'H' => 8,    // %
            'I' => 18,   // Prévis N
            'J' => 18,   // Réalis N
            'K' => 8,    // %
            'L' => 18,   // Prévis N+1
            'M' => 18,   // Prévis N+2
            'N' => 22,   // Total N+1/N+2
            'O' => 25,   // Total N/N+1/N+2
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Titre
            1 => [
                'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1e3a5f']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // En-tête colonnes (ligne 4)
            4 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e3a5f']],
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

                // Formater toutes les colonnes numériques
                $numCols = ['C', 'D', 'F', 'G', 'I', 'J', 'L', 'M', 'N', 'O'];
                foreach ($numCols as $col) {
                    $sheet->getStyle("{$col}5:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode($numFormat);
                }

                // Merger le titre sur toutes les colonnes
                $sheet->mergeCells('A1:O1');
                $sheet->mergeCells('A2:O2');

                // Hauteur des lignes d'en-tête
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(4)->setRowHeight(35);

                // Figer les 4 premières lignes et les 2 premières colonnes
                $sheet->freezePane('C5');

                // Style total général (dernière ligne)
                $sheet->getStyle("A{$lastRow}:O{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e3a5f']],
                ]);

                // Bordures globales
                $sheet->getStyle("A4:O{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);

                // Colonnes prévisions N+1/N+2 en jaune (à saisir)
                $sheet->getStyle("L5:M{$lastRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFDE7']],
                ]);

                // Colonne rubrique — alignement gauche
                $sheet->getStyle("B5:B{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Police générale
                $sheet->getStyle("A1:O{$lastRow}")
                    ->getFont()->setName('Arial')->setSize(9);
            },
        ];
    }
}
