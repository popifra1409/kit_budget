<?php

namespace App\Exports;

use App\Models\CbmtExercice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Équilibre ressources − dépenses par année projetée (CbmtExercice::getTestSoutenabilite()). */
class CbmtEquilibreSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected CbmtExercice $cbmt) {}

    public function headings(): array
    {
        return ['Année', 'Ressources', 'Dépenses', 'Écart', 'Situation'];
    }

    public function array(): array
    {
        $n = $this->cbmt->anneeReference();
        $test = $this->cbmt->getTestSoutenabilite();

        return collect(['montant_n_plus_1' => 1, 'montant_n_plus_2' => 2, 'montant_n_plus_3' => 3])
            ->map(fn($d, $col) => [
                $n + $d,
                (float) $test[$col]['ressources'],
                (float) $test[$col]['depenses'],
                (float) $test[$col]['ecart'],
                abs((float) $test[$col]['ecart']) < 1 ? 'Équilibré' : 'Déséquilibré',
            ])
            ->values()->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('B2:D4')->getNumberFormat()->setFormatCode('#,##0');

        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Équilibre';
    }
}
