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
        $annee = $this->t['exercice']?->annee;

        // Une feuille par sous-programme, dans l'ordre de l'état PDF
        $pris = [];
        $sheets = $sps->map(function ($b) use (&$pris, $annee) {
            $nom = $this->nomOnglet($b['sp_texte'], $pris);
            $pris[] = $nom;

            return new LibellesSousProgrammeSheet($b, $nom, $annee);
        })->all();

        // Feuille de rattrapage : toutes les observations, filtrables et triables
        $sheets[] = new ArraySheet('Observations', [
            'Sous-programme',
            'Responsable',
            'Niveau',
            'Libellé',
            'Observation',
        ], $sps->flatMap(fn($b) => $b['observations']->map(fn($o) => [
            $b['sp_texte'],
            $b['responsable'] ?? 'Non désigné',
            $o['niveau'],
            $o['libelle'],
            $o['constat'],
        ]))->all());

        return $sheets;
    }

    /**
     * Nom d'onglet Excel : 31 caractères maximum, sans : \ / ? * [ ], et unique dans le classeur
     * (deux sous-programmes peuvent partager leurs 31 premiers caractères).
     */
    protected function nomOnglet(string $texte, array $pris): string
    {
        $nom = trim(preg_replace('/[\s:\/\\\\\?\*\[\]]+/u', ' ', $texte));

        if (mb_strlen($nom) > 31) {
            // coupure sur un mot plutôt qu'en plein milieu du libellé
            $nom = mb_substr($nom, 0, 31);
            $espace = mb_strrpos($nom, ' ');
            if ($espace !== false && $espace >= 8) {
                $nom = mb_substr($nom, 0, $espace);
            }
        }
        $nom = rtrim($nom, ' -');

        if (!in_array($nom, $pris, true)) {
            return $nom;
        }

        $base = mb_substr($nom, 0, 27);
        $i = 2;
        while (in_array("{$base} ({$i})", $pris, true)) {
            $i++;
        }

        return "{$base} ({$i})";
    }
}
