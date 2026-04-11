<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServicesTemplateExport implements FromCollection, WithHeadings, WithStyles
{
    public function collection()
    {
        // Retourner 3 lignes d'exemple
        return collect([
            [
                'code' => 'SRV-001',
                'nom' => 'Service Informatique',
                'description' => 'Gestion du parc informatique',
                'responsable' => 'Jean Dupont',
                'email' => 'jean.dupont@example.com',
                'telephone' => '+237 6XX XX XX XX',
                'batiment' => 'Bâtiment A',
                'bureau' => 'Bureau 201',
                'actif' => '1',
            ],
            [
                'code' => 'SRV-002',
                'nom' => 'Service Comptabilité',
                'description' => 'Gestion comptable',
                'responsable' => 'Marie Martin',
                'email' => 'marie.martin@example.com',
                'telephone' => '+237 6XX XX XX XX',
                'batiment' => 'Bâtiment B',
                'bureau' => 'Bureau 105',
                'actif' => '1',
            ],
            [
                'code' => 'SRV-003',
                'nom' => 'Service Ressources Humaines',
                'description' => 'Gestion du personnel',
                'responsable' => 'Pierre Dubois',
                'email' => 'pierre.dubois@example.com',
                'telephone' => '+237 6XX XX XX XX',
                'batiment' => 'Bâtiment A',
                'bureau' => 'Bureau 301',
                'actif' => '1',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'code',
            'nom',
            'description',
            'responsable',
            'email',
            'telephone',
            'batiment',
            'bureau',
            'actif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']]],
        ];
    }
}
