<?php

namespace App\Exports;

use App\Models\Budget;
use App\Services\Budget\EtatDisponibilitesService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel de l'"Etat des disponibilites budgetaires".
 * Calculs : EtatDisponibilitesService (commun avec le PDF).
 *
 * Colonnes A..O = etat ; P = "Niveau" (filtre : Programme, Ligne, Engagement...).
 */
class DisponibilitesBudgetExport extends DefaultValueBinder implements
    FromArray,
    WithHeadings,
    WithStyles,
    WithTitle,
    WithColumnWidths,
    WithEvents,
    WithCustomValueBinder
{
    protected const DERNIERE_COLONNE_ETAT = 'O';
    protected const COLONNE_NIVEAU = 'P';
    protected const PREMIERE_LIGNE_DONNEES = 4; // apres les 2 lignes de titre + l'en-tete

    protected ?array $etat = null;

    /** Type de chaque ligne de donnees (meme ordre que array()). */
    protected array $types = [];

    public function __construct(
        protected Budget $budget,
        protected bool $detaille = false,
        protected bool $engageesSeulement = false,
        protected ?int $programmeId = null,
    ) {}

    protected function etat(): array
    {
        return $this->etat ??= app(EtatDisponibilitesService::class)
            ->construire($this->budget, $this->detaille, $this->engageesSeulement, $this->programmeId);
    }

    public function headings(): array
    {
        return [
            'Code',
            $this->detaille ? 'Nomenclature / Bénéficiaire — Objet' : 'Nomenclature',
            'Budget Initial',
            'Vir. Entrants',
            'Vir. Sortants',
            'Budget Rectifié',
            'Engagé',
            'Ordonné',
            'Payé',
            'Taxes Reversées (OPT)',
            'Disponible Eng.',
            'Disponible Ord.',
            'Taux Engagement (%)',
            'Taux Ordonnancement (%)',
            'Taux Exécution (%)',
            'Niveau',
        ];
    }

    public function array(): array
    {
        $etat = $this->etat();
        $rows = [];

        foreach ($etat['groupes'] as $groupe) {
            $titre = ($groupe['programme'] ? 'PROGRAMME : ' : '') . $groupe['libelle'];
            $this->ajouterLigne($rows, $this->ligneTitre($titre), 'programme');

            foreach ($groupe['sous_groupes'] as $sousGroupe) {
                if ($sousGroupe['libelle']) {
                    $this->ajouterLigne($rows, $this->ligneTitre('   ↳ Sous-programme (gestion interne) : ' . $sousGroupe['libelle']), 'sous_programme');
                }

                foreach ($sousGroupe['lignes'] as $ligne) {
                    $this->ajouterLigne($rows, array_merge([$ligne['code'], $ligne['libelle']], $this->montants($ligne['c'])), 'ligne');

                    foreach ($ligne['engagements'] as $e) {
                        $this->ajouterLigne($rows, $this->ligneEngagement($e), 'engagement');
                    }
                }

                if ($sousGroupe['libelle']) {
                    $this->ajouterLigne($rows, array_merge(['Sous-total sous-programme', ''], $this->montants($sousGroupe['total'])), 'sous_total');
                }
            }

            if ($groupe['afficher_total']) {
                $this->ajouterLigne($rows, array_merge(['Sous-total programme', ''], $this->montants($groupe['total'])), 'sous_total');
            }
        }

        $this->ajouterLigne($rows, array_merge(['TOTAL GÉNÉRAL', ''], $this->montants($etat['total'])), 'total');

        return $rows;
    }

    // ────────────────────────────────────────────────────────────────

    protected const LIBELLES_NIVEAU = [
        'programme'      => 'Programme',
        'sous_programme' => 'Sous-programme',
        'ligne'          => 'Ligne',
        'engagement'     => 'Engagement',
        'sous_total'     => 'Sous-total',
        'total'          => 'Total',
    ];

    protected function ajouterLigne(array &$rows, array $valeurs, string $type): void
    {
        $valeurs[] = self::LIBELLES_NIVEAU[$type];
        $rows[] = $valeurs;
        $this->types[] = $type;
    }

    protected function ligneTitre(string $libelle): array
    {
        return array_merge([$libelle], array_fill(0, 14, ''));
    }

    protected function montants(array $c): array
    {
        return [
            round($c['budgetInitial'], 2),
            round($c['virementsEntrants'], 2),
            round($c['virementsSortants'], 2),
            round($c['budgetRectifie'], 2),
            round($c['engage'], 2),
            round($c['ordonne'], 2),
            round($c['paye'], 2),
            round($c['taxesReversees'], 2),
            round($c['disponibleEng'], 2),
            round($c['disponibleOrd'], 2),
            round($c['tauxEngagement'], 2),
            round($c['tauxOrdonnancement'], 2),
            round($c['tauxExecution'], 2),
        ];
    }

    protected function ligneEngagement(array $e): array
    {
        $details = array_filter([$e['date'], $e['numeros_op'] ? "OP {$e['numeros_op']}" : null]);

        return [
            $e['numero'],
            '↳ ' . $e['beneficiaire'] . ' — ' . $e['objet'] . ($details ? ' (' . implode(', ', $details) . ')' : ''),
            '',
            '',
            '',
            '',
            round($e['engage'], 2),
            round($e['ordonne'], 2),
            round($e['paye'], 2),
            round($e['taxesReversees'], 2),
            '',
            '',
            '',
            '',
            '',
        ];
    }

    /** Protection contre l'injection de formules (objets et noms saisis par les utilisateurs). */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return $this->detaille ? 'Disponibilités (détail)' : 'Disponibilités';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => $this->detaille ? 60 : 42,
            'C' => 16,
            'D' => 14,
            'E' => 14,
            'F' => 16,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 17,
            'K' => 16,
            'L' => 16,
            'M' => 13,
            'N' => 14,
            'O' => 13,
            'P' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $fin = self::DERNIERE_COLONNE_ETAT;
                $niv = self::COLONNE_NIVEAU;
                $etat = $this->etat();

                // ── Titre et sous-titre ──
                $sheet->insertNewRowBefore(1, 2);
                $sheet->setCellValue('A1', 'ÉTAT DES DISPONIBILITÉS BUDGÉTAIRES' . ($this->detaille ? ' — DÉTAIL DES ENGAGEMENTS' : ''));
                $sheet->mergeCells("A1:{$niv}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E78']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                $options = array_filter([
                    $this->engageesSeulement ? 'Lignes engagées uniquement' : null,
                    $etat['programme_filtre'] ? 'Programme : ' . $etat['programme_filtre'] : null,
                ]);

                $sheet->setCellValue(
                    'A2',
                    "Budget : {$this->budget->libelle} - Exercice : {$this->budget->exercice}"
                        . ' - Lignes : ' . $etat['nb_lignes_affichees'] . ' / ' . $etat['nb_lignes_budget']
                        . ($options ? ' - ' . implode(' - ', $options) : '')
                        . ' - Généré le : ' . now()->format('d/m/Y à H:i')
                );
                $sheet->mergeCells("A2:{$niv}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 10, 'italic' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $premiere = self::PREMIERE_LIGNE_DONNEES;
                $derniere = $sheet->getHighestRow();

                // ── Bordures, formats ──
                $sheet->getStyle("A3:{$niv}{$derniere}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                ]);
                $sheet->getStyle("C{$premiere}:L{$derniere}")->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
                $sheet->getStyle("M{$premiere}:O{$derniere}")->getNumberFormat()->setFormatCode('0.00"%"');
                $sheet->getStyle("C{$premiere}:L{$derniere}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("M{$premiere}:O{$derniere}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("{$niv}{$premiere}:{$niv}{$derniere}")->getFont()->getColor()->setRGB('7F7F7F');

                // ── Styles par type de ligne ──
                foreach ($this->types as $i => $type) {
                    $row = $premiere + $i;

                    match ($type) {
                        'programme' => $this->styleTitre($sheet, $row, $fin, [
                            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
                        ]),
                        'sous_programme' => $this->styleTitre($sheet, $row, $fin, [
                            'font' => ['bold' => true, 'italic' => true, 'color' => ['rgb' => '1F4E78']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
                        ]),
                        'sous_total' => $sheet->getStyle("A{$row}:{$fin}{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'italic' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F2F5']],
                            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN], 'bottom' => ['borderStyle' => Border::BORDER_THIN]],
                        ]),
                        'total' => $sheet->getStyle("A{$row}:{$fin}{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 11],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
                        ]),
                        'engagement' => $sheet->getStyle("A{$row}:{$fin}{$row}")->applyFromArray([
                            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '555555']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
                            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
                        ]),
                        default => null,
                    };

                    // Disponibles negatifs en rouge (K = Dispo. Eng., L = Dispo. Ord.)
                    if (in_array($type, ['ligne', 'sous_total', 'total'], true)) {
                        foreach (['K', 'L'] as $col) {
                            $valeur = $sheet->getCell("{$col}{$row}")->getValue();
                            if (is_numeric($valeur) && $valeur < 0) {
                                $sheet->getStyle("{$col}{$row}")->applyFromArray([
                                    'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
                                ]);
                            }
                        }
                    }

                    if ($type !== 'engagement') {
                        $sheet->getRowDimension($row)->setRowHeight(16);
                    }
                }

                $sheet->getRowDimension(3)->setRowHeight(32);
                $sheet->freezePane('C' . $premiere);
                $sheet->setAutoFilter("A3:{$niv}{$derniere}");
            },
        ];
    }

    protected function styleTitre(Worksheet $sheet, int $row, string $fin, array $style): void
    {
        $sheet->mergeCells("A{$row}:{$fin}{$row}");
        $sheet->getStyle("A{$row}:{$fin}{$row}")->applyFromArray($style);
    }
}
