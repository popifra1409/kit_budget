<?php

namespace App\Exports;

use App\Models\Budget;
use App\Models\Engagement;
use App\Models\OrdonnancePaiement;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export "Etat des disponibilites budgetaires", regroupe par Programme
 * de rattachement puis par Sous-programme de gestion interne (le cas
 * echeant).
 *
 * "Paye" = montant net verse au beneficiaire (OP standard, statut
 * 'payee'). "Taxes Reversees" = retenues (TVA, IR, TSR...) reversees
 * separement au Tresor via l'OPT liee. Colonnes Chapitre/Article/
 * Paragraphe retirees (non utilisees).
 */
class DisponibilitesBudgetExport implements
    FromArray,
    WithHeadings,
    WithStyles,
    WithTitle,
    WithColumnWidths,
    WithEvents
{
    protected Budget $budget;

    protected array $lignesProgramme = [];
    protected array $lignesSousProgramme = [];
    protected array $lignesSousTotal = [];
    protected int $ligneTotalGeneral = 0;

    public function __construct(Budget $budget)
    {
        $this->budget = $budget;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Nomenclature',
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
            'Taux Exécution (%)',
        ];
    }

    protected function calculerLigne($ligne): array
    {
        $budgetInitial = (float) ($ligne->budget_initial ?? 0);
        $virementsEntrants = (float) ($ligne->virements_entrants ?? 0);
        $virementsSortants = (float) ($ligne->virements_sortants ?? 0);
        $budgetRectifie = (float) ($ligne->budget_rectifie ?? ($budgetInitial + $virementsEntrants - $virementsSortants));

        $engage = (float) (Engagement::where('budget_id', $ligne->budget_id)
            ->where('nomenclature_principale_id', $ligne->nomenclature_id)
            ->sum('montant_engage') ?? 0);

        $ordonne = (float) (Engagement::where('budget_id', $ligne->budget_id)
            ->where('nomenclature_principale_id', $ligne->nomenclature_id)
            ->whereHas('ordonnancesPaiement')
            ->sum('montant_engage') ?? 0);

        $paye = (float) (OrdonnancePaiement::whereHas('engagement', function ($q) use ($ligne) {
            $q->where('budget_id', $ligne->budget_id)
                ->where('nomenclature_principale_id', $ligne->nomenclature_id);
        })
            ->where('type_ordonnance', 'standard')
            ->where('statut', 'payee')
            ->sum('montant_net') ?? 0);

        $taxesReversees = (float) (OrdonnancePaiement::whereHas('engagement', function ($q) use ($ligne) {
            $q->where('budget_id', $ligne->budget_id)
                ->where('nomenclature_principale_id', $ligne->nomenclature_id);
        })
            ->where('type_ordonnance', 'impot')
            ->where('statut', 'payee')
            ->sum('montant_net') ?? 0);

        $disponibleEng = $budgetRectifie - $engage;
        $disponibleOrd = $budgetRectifie - $ordonne;
        $tauxEngagement = $budgetRectifie > 0 ? ($engage / $budgetRectifie) * 100 : 0;
        $tauxExecution = $budgetRectifie > 0 ? (($paye + $taxesReversees) / $budgetRectifie) * 100 : 0;

        return compact(
            'budgetInitial',
            'virementsEntrants',
            'virementsSortants',
            'budgetRectifie',
            'engage',
            'ordonne',
            'paye',
            'taxesReversees',
            'disponibleEng',
            'disponibleOrd',
            'tauxEngagement',
            'tauxExecution'
        );
    }

    protected function ligneVide(string $libelle, array $totaux = []): array
    {
        return [
            $libelle,
            '',
            $totaux['budgetInitial'] ?? '',
            $totaux['virementsEntrants'] ?? '',
            $totaux['virementsSortants'] ?? '',
            $totaux['budgetRectifie'] ?? '',
            $totaux['engage'] ?? '',
            $totaux['ordonne'] ?? '',
            $totaux['paye'] ?? '',
            $totaux['taxesReversees'] ?? '',
            $totaux['disponibleEng'] ?? '',
            $totaux['disponibleOrd'] ?? '',
            $totaux['tauxEngagement'] ?? '',
            $totaux['tauxExecution'] ?? '',
        ];
    }

    protected function ligneDonnee($ligne, array $c): array
    {
        return [
            $ligne->nomenclature?->code ?? '',
            $ligne->nomenclature?->libelle ?? '',
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
            round($c['tauxExecution'], 2),
        ];
    }

    public function array(): array
    {
        $lignes = $this->budget->lignesBudgetaires()->with(['nomenclature'])->get();

        $enrichies = $lignes->map(function ($ligne) {
            $c = $this->calculerLigne($ligne);
            $classification = $ligne->getClassificationStrategique();
            return (object) array_merge($c, [
                'ligne' => $ligne,
                'programme' => $classification['programme'],
                'sous_programme' => $classification['sous_programme'],
            ]);
        });

        $groupesProgramme = $enrichies->groupBy(fn($l) => $l->programme?->id ?? 'non_affecte');

        $rows = [];
        $rowIndex = 1;

        $totalGeneral = ['budgetInitial' => 0, 'virementsEntrants' => 0, 'virementsSortants' => 0, 'budgetRectifie' => 0, 'engage' => 0, 'ordonne' => 0, 'paye' => 0, 'taxesReversees' => 0, 'disponibleEng' => 0, 'disponibleOrd' => 0];

        foreach ($groupesProgramme as $programmeId => $lignesDuProgramme) {
            $programmeLabel = $programmeId === 'non_affecte'
                ? 'NON AFFECTE A UN PROGRAMME'
                : 'PROGRAMME : ' . $lignesDuProgramme->first()->programme->code . ' - ' . $lignesDuProgramme->first()->programme->libelle;

            $rows[] = $this->ligneVide($programmeLabel);
            $rowIndex++;
            $this->lignesProgramme[] = $rowIndex + 1;

            $sousGroupes = $lignesDuProgramme->groupBy(fn($l) => $l->sous_programme?->id ?? 'sans_sous_programme');

            foreach ($sousGroupes as $sousProgrammeId => $lignesDuSousGroupe) {
                if ($sousProgrammeId !== 'sans_sous_programme') {
                    $sp = $lignesDuSousGroupe->first()->sous_programme;
                    $rows[] = $this->ligneVide("   -> Sous-programme : {$sp->code} - {$sp->libelle}");
                    $rowIndex++;
                    $this->lignesSousProgramme[] = $rowIndex + 1;
                }

                $sTotal = ['budgetInitial' => 0, 'virementsEntrants' => 0, 'virementsSortants' => 0, 'budgetRectifie' => 0, 'engage' => 0, 'ordonne' => 0, 'paye' => 0, 'taxesReversees' => 0, 'disponibleEng' => 0, 'disponibleOrd' => 0];

                foreach ($lignesDuSousGroupe->sortBy(fn($l) => $l->ligne->nomenclature?->code ?? 'ZZZ') as $l) {
                    $c = (array) $l;
                    $rows[] = $this->ligneDonnee($l->ligne, $c);
                    $rowIndex++;

                    foreach (['budgetInitial', 'virementsEntrants', 'virementsSortants', 'budgetRectifie', 'engage', 'ordonne', 'paye', 'taxesReversees', 'disponibleEng', 'disponibleOrd'] as $k) {
                        $sTotal[$k] += $c[$k];
                        $totalGeneral[$k] += $c[$k];
                    }
                }

                if ($sousProgrammeId !== 'sans_sous_programme') {
                    $sTotal['tauxEngagement'] = $sTotal['budgetRectifie'] > 0 ? ($sTotal['engage'] / $sTotal['budgetRectifie']) * 100 : 0;
                    $sTotal['tauxExecution'] = $sTotal['budgetRectifie'] > 0 ? (($sTotal['paye'] + $sTotal['taxesReversees']) / $sTotal['budgetRectifie']) * 100 : 0;
                    $rows[] = $this->ligneVide('Sous-total sous-programme', array_map(fn($v) => round($v, 2), $sTotal));
                    $rowIndex++;
                    $this->lignesSousTotal[] = $rowIndex + 1;
                }
            }
        }

        $totalGeneral['tauxEngagement'] = $totalGeneral['budgetRectifie'] > 0 ? ($totalGeneral['engage'] / $totalGeneral['budgetRectifie']) * 100 : 0;
        $totalGeneral['tauxExecution'] = $totalGeneral['budgetRectifie'] > 0 ? (($totalGeneral['paye'] + $totalGeneral['taxesReversees']) / $totalGeneral['budgetRectifie']) * 100 : 0;
        $rows[] = $this->ligneVide('TOTAL GENERAL', array_map(fn($v) => round($v, 2), $totalGeneral));
        $rowIndex++;
        $this->ligneTotalGeneral = $rowIndex + 1;

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Disponibilités';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 42,
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
            'N' => 13,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = 'N';

                $sheet->insertNewRowBefore(1, 2);
                $sheet->setCellValue('A1', 'ÉTAT DES DISPONIBILITÉS BUDGÉTAIRES');
                $sheet->mergeCells('A1:' . $highestColumn . '1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E78']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                $sheet->setCellValue('A2', "Budget: {$this->budget->libelle} - Exercice: {$this->budget->exercice} - Généré le: " . now()->format('d/m/Y à H:i'));
                $sheet->mergeCells('A2:' . $highestColumn . '2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 10, 'italic' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $highestRow = $sheet->getHighestRow();

                $sheet->getStyle('A3:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                ]);

                // Montants : colonnes C a L. Pourcentages : M, N.
                $sheet->getStyle('C4:L' . $highestRow)->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
                $sheet->getStyle('M4:N' . $highestRow)->getNumberFormat()->setFormatCode('0.00"%"');
                $sheet->getStyle('C4:L' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('M4:N' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach ($this->lignesProgramme as $r) {
                    $row = $r + 2;
                    $sheet->mergeCells("A{$row}:N{$row}");
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
                    ]);
                }

                foreach ($this->lignesSousProgramme as $r) {
                    $row = $r + 2;
                    $sheet->mergeCells("A{$row}:N{$row}");
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'italic' => true, 'color' => ['rgb' => '1F4E78']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
                    ]);
                }

                foreach ($this->lignesSousTotal as $r) {
                    $row = $r + 2;
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'italic' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F2F5']],
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN], 'bottom' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                }

                $totalRow = $this->ligneTotalGeneral + 2;
                $sheet->getStyle("A{$totalRow}:N{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
                ]);

                // Disponibles negatifs en rouge : Disponible Eng. = K, Disponible Ord. = L
                for ($row = 4; $row <= $highestRow; $row++) {
                    $dispoEng = $sheet->getCell('K' . $row)->getValue();
                    $dispoOrd = $sheet->getCell('L' . $row)->getValue();

                    if (is_numeric($dispoEng) && $dispoEng < 0) {
                        $sheet->getStyle('K' . $row)->applyFromArray([
                            'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
                        ]);
                    }
                    if (is_numeric($dispoOrd) && $dispoOrd < 0) {
                        $sheet->getStyle('L' . $row)->applyFromArray([
                            'font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
                        ]);
                    }
                }

                $sheet->freezePane('A4');

                for ($row = 4; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(16);
                }
            },
        ];
    }
}
