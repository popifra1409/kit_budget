<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Feuille générique de l'export de clôture : en-têtes en gras, lignes de titre/total en gras, montants formatés. */
class ClotureFeuille implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected string $titre,
        protected array $enTetes,
        protected array $lignes,
        protected array $lignesEnGras = [],
        protected string $premiereColonneMontant = 'B',
    ) {}

    public function array(): array
    {
        return $this->lignes;
    }
    public function headings(): array
    {
        return $this->enTetes;
    }
    public function title(): string
    {
        return mb_substr($this->titre, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $derniere = $sheet->getHighestColumn();
        $hauteur = max(2, $sheet->getHighestRow());
        $sheet->getStyle("{$this->premiereColonneMontant}2:{$derniere}{$hauteur}")->getNumberFormat()->setFormatCode('#,##0.##');

        $styles = [1 => ['font' => ['bold' => true]]];
        foreach ($this->lignesEnGras as $ligne) {
            $styles[$ligne] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
