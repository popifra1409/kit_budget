<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ArborescenceLibellesExport implements WithMultipleSheets
{
    public function __construct(protected array $t) {}

    public function sheets(): array
    {
        $sps = $this->t['sous_programmes'];

        // Une ligne par sous-tache, tous les niveaux repetes (filtrable et triable dans Excel)
        $libelles = $sps->flatMap(fn($b) => collect($b['lignes'])->map(fn($r) => [
            $b['sp_texte'],
            $b['programme'] ?? '',
            $b['sp']->responsable?->name ?? '',
            $r['ctx']['action'] ?? '',
            $r['ctx']['activite'] ?? '',
            $r['ctx']['extrants'] ?? '',
            $r['ctx']['indicateurs'] ?? '',
            $r['ctx']['tache'] ?? '',
            $r['ligne']['sous_tache'] ?? ($r['message'] ?? ''),
            $r['ligne']['nomenclature'] ?? '',
        ]))->all();

        $observations = $sps->flatMap(fn($b) => $b['observations']->map(fn($o) => [
            $b['sp_texte'],
            $b['sp']->responsable?->name ?? '',
            $o['niveau'],
            $o['libelle'],
            $o['constat'],
        ]))->all();

        return [
            new ArraySheet('Libellés', [
                'Sous-programme',
                'Programme de rattachement',
                'Responsable',
                'Action',
                'Activité',
                'Extrant(s)',
                'Indicateur(s)',
                'Tâche',
                'Sous-tâche',
                'Ligne budgétaire',
            ], $libelles),

            new ArraySheet('Observations', [
                'Sous-programme',
                'Responsable',
                'Niveau',
                'Libellé',
                'Observation',
            ], $observations),
        ];
    }
}
