<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MatriceArrimageExport implements WithMultipleSheets
{
    public function __construct(protected array $m) {}

    public function sheets(): array
    {
        $sps = $this->m['sous_programmes'];

        $synthese = $sps->map(fn($b) => [
            $b['sp']->code,
            $b['sp']->libelle,
            $b['sp']->programmeBudgetaire?->code,
            $b['sp']->responsable?->name,
            $b['compteurs']['actions'],
            $b['compteurs']['activites'],
            $b['compteurs']['taches'],
            $b['compteurs']['extrants'],
            $b['compteurs']['indicateurs'],
            $b['ae'],
            $b['cp'],
            $b['engage'],
            $b['taux_execution'],
            $b['part_budget'],
            $b['score_completude'],
            $b['score_performance'],
            $b['score_extrants'],
            $b['anomalies']->count(),
            $b['appreciation']['libelle'],
        ])->all();

        // Detail "a plat" : une ligne par sous-tache, toutes colonnes renseignees (ideal pour TCD)
        $detail = $sps->flatMap(fn($b) => collect($b['lignes'])->map(fn($r) => [
            $b['sp']->code,
            $b['sp']->libelle,
            $b['sp']->responsable?->name,
            $b['sp']->programmeBudgetaire?->code,
            $r['ctx']['action'] ?? '',
            $r['ctx']['activite'] ?? '',
            $r['ctx']['responsable_activite'] ?? '',
            $r['ctx']['extrants'] ?? '',
            $r['ctx']['indicateurs'] ?? '',
            $r['ctx']['tache'] ?? '',
            $r['ligne']['sous_tache'] ?? $r['message'],
            $r['ligne']['code'] ?? '',
            $r['ligne']['nomenclature'] ?? '',
            $r['ligne']['ae'] ?? null,
            $r['ligne']['cp'] ?? null,
            $r['ligne']['engage'] ?? null,
            $r['ligne']['taux'] ?? null,
            isset($r['ligne']['quote_part']) ? round($r['ligne']['quote_part'] * 100, 1) : null,
        ]))->all();

        $anomalies = $sps->flatMap(fn($b) => $b['anomalies']->map(fn($a) => [
            $b['sp']->code,
            $b['sp']->libelle,
            $b['sp']->responsable?->name,
            ucfirst($a['gravite']),
            $a['message'],
        ]))->all();

        return [
            new ArraySheet('Synthèse', [
                'Code SP',
                'Sous-programme',
                'Programme',
                'Responsable',
                'Actions',
                'Activités',
                'Tâches',
                'Extrants',
                'Indicateurs',
                'AE',
                'CP',
                'Engagé',
                'Exécution (%)',
                'Part budget PSP (%)',
                'Complétude (%)',
                'Atteinte indicateurs (%)',
                'Réalisation extrants (%)',
                'Nb anomalies',
                'Appréciation',
            ], $synthese),

            new ArraySheet('Détail', [
                'Code SP',
                'Sous-programme',
                'Responsable SP',
                'Programme',
                'Action',
                'Activité',
                'Responsable activité',
                'Extrants',
                'Indicateurs',
                'Tâche',
                'Sous-tâche',
                'Code nomenclature',
                'Ligne budgétaire',
                'AE',
                'CP',
                'Engagé',
                'Taux (%)',
                'Quote-part (%)',
            ], $detail),

            new ArraySheet('Anomalies', ['Code SP', 'Sous-programme', 'Responsable', 'Gravité', 'Constat'], $anomalies),
        ];
    }
}
