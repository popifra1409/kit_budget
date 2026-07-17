<?php

namespace App\Exports;

use App\Models\OrdonnancePaiement;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class OrdonnancesPaiementExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithTitle,
    WithEvents
{
    use Exportable;

    // Totaux pour la ligne de récap
    protected float $totalBrut  = 0;
    protected float $totalPrecompte = 0;
    protected float $totalNet   = 0;
    protected int   $rowCount   = 0;

    public function __construct(
        protected ?string $typeOrdonnance = null, // 'standard' | 'impot' | null (les deux)
        protected ?string $dateDebut      = null,
        protected ?string $dateFin        = null,
        protected ?string $mois           = null,
        protected ?string $annee          = null,
        protected ?string $statut         = null, // ✅ NOUVEAU : 'payee' | 'emise' | 'visee' | null (tous)
        protected ?string $titre          = null, // Titre personnalisé pour l'en-tête
    ) {}

    // Cache des OPT liées par engagement_id
    private array $_optsCache = [];
    private bool  $_optsCacheLoaded = false;

    private function loadOptsCache(): void
    {
        if ($this->_optsCacheLoaded) return;
        // Charger toutes les OPT pour calculer précompte
        $opts = OrdonnancePaiement::where('type_ordonnance', 'impot')
            ->get(['engagement_id', 'montant_net']);
        foreach ($opts as $opt) {
            if ($opt->engagement_id) {
                $this->_optsCache[$opt->engagement_id] = (float) $opt->montant_net;
            }
        }
        $this->_optsCacheLoaded = true;
    }

    public function query()
    {
        $query = OrdonnancePaiement::query()
            ->with(['engagement', 'beneficiaire', 'exercice'])
            ->whereIn('type_ordonnance', ['standard', 'impot']);

        // ✅ Filtre par type OP/OPT
        if ($this->typeOrdonnance) {
            $query->where('type_ordonnance', $this->typeOrdonnance);
        }

        // ✅ Filtre par statut (payée / émise / visée / tous)
        if ($this->statut) {
            $query->where('statut', $this->statut);
        }

        // Filtre par mois et année
        if ($this->mois && $this->annee) {
            $query->whereMonth('date_emission', $this->mois)
                ->whereYear('date_emission', $this->annee);
        } elseif ($this->dateDebut && $this->dateFin) {
            $query->whereBetween('date_emission', [$this->dateDebut, $this->dateFin]);
        }

        return $query->orderBy('date_emission', 'desc')->orderBy('numero', 'asc');
    }

    public function headings(): array
    {
        // ✅ Sans colonnes Type et Statut
        return [
            'N° OP/OPT',
            'Date Émission',
            'N° Engagement',
            'Objet',
            'Bénéficiaire',
            'Montant Brut (FCFA)',
            'À Précompter (FCFA)',
            'Net à Payer (FCFA)',
            'Mode de Paiement',
            'Date Paiement',
            'Référence Paiement',
            'Exercice',
        ];
    }

    public function map($ordonnance): array
    {
        // ✅ Calculer précompte depuis OPT liée (montant_brut = 0 en DB)
        $this->loadOptsCache();
        $precompte = $this->_optsCache[$ordonnance->engagement_id ?? 0]
            ?? (float) $ordonnance->montant_impot;
        $brut = (float) $ordonnance->montant_net + $precompte;

        // Accumuler les totaux
        $this->totalBrut      += $brut;
        $this->totalPrecompte += $precompte;
        $this->totalNet       += (float) $ordonnance->montant_net;
        $this->rowCount++;

        return [
            $ordonnance->numero,
            $ordonnance->date_emission
                ? \Carbon\Carbon::parse($ordonnance->date_emission)->format('d/m/Y') : '',
            $ordonnance->engagement?->numero ?? '',
            $ordonnance->objet,
            $this->getBeneficiaireNom($ordonnance),
            $brut,
            $precompte,
            (float) $ordonnance->montant_net,
            $this->getModePaiementLabel($ordonnance->mode_paiement),
            $ordonnance->date_paiement
                ? \Carbon\Carbon::parse($ordonnance->date_paiement)->format('d/m/Y') : '—',
            $ordonnance->reference_paiement ?? '—',
            $ordonnance->exercice?->annee ?? '',
        ];
    }

    protected function getBeneficiaireNom($ordonnance): string
    {
        if ($ordonnance->type_ordonnance === 'impot') {
            return 'TRÉSOR PUBLIC / DGI';
        }
        if (!$ordonnance->beneficiaire) return 'N/A';
        return $ordonnance->beneficiaire->raison_sociale
            ?? $ordonnance->beneficiaire->nom_complet
            ?? $ordonnance->beneficiaire->name
            ?? 'N/A';
    }

    protected function getModePaiementLabel(?string $mode): string
    {
        return match ($mode) {
            'virement'       => 'Virement bancaire',
            'cheque'         => 'Chèque',
            'especes'        => 'Espèces',
            'ordre_virement' => 'Ordre de virement',
            'mandat'         => 'Mandat postal',
            'mobile_money'   => 'Mobile Money',
            'autre'          => 'Autre',
            null, ''         => '—',
            default          => ucfirst($mode),
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Ligne titre (1)
            1 => [
                'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Ligne sous-titre (2)
            2 => [
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '374151']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'dbeafe']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // En-tête colonnes (3)
            3 => [
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563eb']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
                'borders'   => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1e3a5f']],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,  // N° OP
            'B' => 14,  // Date émission
            'C' => 18,  // N° Engagement
            'D' => 38,  // Objet
            'E' => 28,  // Bénéficiaire
            'F' => 18,  // Montant Brut
            'G' => 18,  // À Précompter
            'H' => 18,  // Net à Payer
            'I' => 18,  // Mode Paiement
            'J' => 14,  // Date Paiement
            'K' => 20,  // Référence
            'L' => 10,  // Exercice
        ];
    }

    public function title(): string
    {
        if ($this->typeOrdonnance === 'standard') return 'OP Standard';
        if ($this->typeOrdonnance === 'impot')    return 'OPT (Impôts)';
        return 'Ordonnances Paiement';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // ── Titre principal (ligne 1) ─────────────────────
                $typeLabel   = match ($this->typeOrdonnance) {
                    'standard' => 'ORDONNANCES DE PAIEMENT (OP Standard)',
                    'impot'    => 'ORDONNANCES DE PAIEMENT D\'IMPÔTS (OPT)',
                    default    => 'ÉTAT DES ORDONNANCES DE PAIEMENT',
                };
                $statutLabel = match ($this->statut) {
                    'payee'  => ' — Payées',
                    'emise'  => ' — Émises (non payées)',
                    'visee'  => ' — Visées',
                    default  => ' — Tous statuts',
                };
                $periodeLabel = '';
                if ($this->mois && $this->annee) {
                    $moisNom = \Carbon\Carbon::createFromDate($this->annee, $this->mois, 1)
                        ->locale('fr')->translatedFormat('F Y');
                    $periodeLabel = ' | Période : ' . $moisNom;
                } elseif ($this->dateDebut && $this->dateFin) {
                    $periodeLabel = ' | Du ' . \Carbon\Carbon::parse($this->dateDebut)->format('d/m/Y')
                        . ' au ' . \Carbon\Carbon::parse($this->dateFin)->format('d/m/Y');
                }

                // Insérer 3 lignes en haut (titre, sous-titre, en-tête)
                $sheet->insertNewRowBefore(1, 3);
                $lastRow += 3;
                $dataEnd = $lastRow;

                // Titre
                $sheet->setCellValue('A1', $typeLabel . $statutLabel);
                $sheet->mergeCells('A1:L1');

                // Sous-titre
                $sheet->setCellValue('A2', $this->titre ?? ('Budget CHUY' . $periodeLabel . ' | Généré le ' . now()->format('d/m/Y à H:i')));
                $sheet->mergeCells('A2:L2');

                // ── Formater colonnes montants ─────────────────────
                $montantFormat = '#,##0';
                foreach (['F', 'G', 'H'] as $col) {
                    $sheet->getStyle("{$col}4:{$col}{$dataEnd}")
                        ->getNumberFormat()->setFormatCode($montantFormat);
                }

                // ── Ligne TOTAL ───────────────────────────────────
                $totalRow = $dataEnd + 1;
                $sheet->setCellValue("A{$totalRow}", 'TOTAL GÉNÉRAL');
                $sheet->setCellValue("E{$totalRow}", $this->rowCount . ' ordonnance(s)');
                $sheet->setCellValue("F{$totalRow}", $this->totalBrut);
                $sheet->setCellValue("G{$totalRow}", $this->totalPrecompte);
                $sheet->setCellValue("H{$totalRow}", $this->totalNet);
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}");

                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
                    ],
                ]);

                $sheet->getStyle("F{$totalRow}:H{$totalRow}")
                    ->getNumberFormat()->setFormatCode($montantFormat);

                // ── Bordures sur les données ──────────────────────
                $sheet->getStyle("A4:L{$dataEnd}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                    ],
                ]);

                // ── Alternance couleurs lignes ────────────────────
                for ($row = 4; $row <= $dataEnd; $row++) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                        ]);
                    }
                }

                // ── Colonnes montants — alignement droite ─────────
                foreach (['F', 'G', 'H'] as $col) {
                    $sheet->getStyle("{$col}4:{$col}{$dataEnd}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ── Figer les 3 premières lignes ─────────────────
                $sheet->freezePane('A4');

                // ── Hauteurs ─────────────────────────────────────
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension(3)->setRowHeight(30);

                // ── Police globale ────────────────────────────────
                $sheet->getStyle("A1:L{$totalRow}")
                    ->getFont()->setName('Arial')->setSize(9);

                // Styles titre
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '1e3a5f']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'dbeafe']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A3:L3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563eb']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                ]);
            },
        ];
    }
}
