<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArraySheet extends DefaultValueBinder implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithCustomValueBinder
{
    public function __construct(
        protected string $titre,
        protected array $entetes,
        protected array $lignes,
    ) {}

    public function array(): array
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return $this->entetes;
    }

    public function title(): string
    {
        return mb_substr($this->titre, 0, 31);
    } // limite Excel

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }

    /** Tout texte commencant par '=' est force en texte : jamais interprete comme formule. */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
