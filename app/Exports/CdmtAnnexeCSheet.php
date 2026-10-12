<?php

namespace App\Exports;

use App\Models\CdmtExercice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Annexe C — programmation par tâche et code budgétaire, regroupée par sous-programme,
 * avec sous-totaux par sous-programme et total général.
 */
class CdmtAnnexeCSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    protected array $lignesEnGras = [];

    public function __construct(protected CdmtExercice $cdmt) {}

    protected const MONTANTS = ['n_ae', 'n_cp', 'n_plus_1_ae', 'n_plus_1_cp', 'n_plus_2_ae', 'n_plus_2_cp', 'n_plus_3_ae', 'n_plus_3_cp'];

    public function headings(): array
    {
        $n = $this->cdmt->anneeReference();

        return [
            'Sous-programme',
            'Action',
            'Activité',
            'Compte',
            'Tâche',
            'Titre',
            'LR/MN',
            'Maturité',
            "{$n} AE",
            "{$n} CP",
            ($n + 1) . ' AE',
            ($n + 1) . ' CP',
            ($n + 2) . ' AE',
            ($n + 2) . ' CP',
            ($n + 3) . ' AE',
            ($n + 3) . ' CP'
        ];
    }

    public function array(): array
    {
        $lignes = $this->cdmt->lignes()->with(['sousProgrammeEp', 'action', 'activite'])->orderBy('ordre')->get()
            ->groupBy('sous_programme_ep_id');
        $sortie = [];
        $rang = 2;

        foreach ($lignes as $groupe) {
            $sp = $groupe->first()->sousProgrammeEp;
            foreach ($groupe as $l) {
                $sortie[] = array_merge([
                    $sp ? "{$sp->code} — {$sp->libelle}" : '—',
                    $l->action ? "{$l->action->code} — {$l->action->libelle}" : '',
                    $l->activite ? "{$l->activite->code} — {$l->activite->libelle}" : '',
                    (string) $l->code,
                    $l->libelle,
                    $l->titre ? 'T' . $l->titre : '',
                    $l->nature,
                    $l->est_investissement ? ($l->maturite ?? '') : '',
                ], array_map(fn($c) => (float) $l->{$c}, self::MONTANTS));
                $rang++;
            }
            $sortie[] = array_merge(
                ['Sous-total ' . ($sp?->code ?? '—'), '', '', '', '', '', '', ''],
                array_map(fn($c) => (float) $groupe->sum($c), self::MONTANTS)
            );
            $this->lignesEnGras[] = $rang++;
        }

        $tout = $lignes->flatten(1);
        $sortie[] = array_merge(['TOTAL', '', '', '', '', '', '', ''], array_map(fn($c) => (float) $tout->sum($c), self::MONTANTS));
        $this->lignesEnGras[] = $rang;

        return $sortie;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('I2:P' . $sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0');

        $styles = [1 => ['font' => ['bold' => true]]];
        foreach ($this->lignesEnGras as $l) {
            $styles[$l] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    public function title(): string
    {
        return 'Annexe C';
    }
}
