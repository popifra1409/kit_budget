<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Feuille « un sous-programme » du tableau de revue des libellés.
 *
 * Même regroupement que le PDF (Action / Activité / Extrants / Indicateurs / Tâche / Sous-tâche /
 * Ligne budgétaire) : ici les niveaux parents sont fusionnés verticalement sur leurs lignes au lieu
 * d'être répétés, et les observations du sous-programme suivent son tableau dans la même feuille.
 */
class LibellesSousProgrammeSheet extends DefaultValueBinder implements
    FromArray, WithTitle, WithColumnWidths, WithStyles, WithEvents, WithCustomValueBinder
{
    protected const COLONNES = [
        'Action', 'Activité', 'Extrant(s)', 'Indicateur(s)', 'Tâche', 'Sous-tâche', 'Ligne budgétaire',
    ];

    // 1 titre · 3..8 en-tête du sous-programme · 9 compteurs · 10 vide · 11 en-têtes · 12 premières données
    protected const TITRE = 1;
    protected const ENTETE = 3;
    protected const COMPTEURS = 9;
    protected const EN_TETES = 11;
    protected const PREMIERE_LIGNE = 12;

    /** Largeur utile (caractères) de chaque colonne : sert à calculer la hauteur des lignes. */
    protected const LARGEURS = ['A' => 24, 'B' => 30, 'C' => 26, 'D' => 26, 'E' => 26, 'F' => 26, 'G' => 24];

    protected array $fusions = [];      // plages à fusionner
    protected array $alertes = [];      // plages « libellé à revoir » (fond jaune)
    protected array $manquants = [];    // plages « Non imputée / Aucun / Non désigné » (rouge italique)
    protected array $messages = [];     // constats de regroupement, en gris italique centré
    protected array $gras = [];         // plages en gras (action, activité)
    protected array $besoins = [];      // ligne => lignes de texte nécessaires
    protected array $groupes = [];      // fusions verticales : [début, fin, lignes de texte]
    protected int $derniereLigne = self::PREMIERE_LIGNE;
    protected int $titreObservations = 0;
    protected int $entetesObservations = 0;
    protected int $derniereObservation = 0;

    public function __construct(protected array $bloc, protected string $titre, protected ?int $annee) {}

    public function title(): string
    {
        return $this->titre;
    }

    public function array(): array
    {
        $b = $this->bloc;

        $lignes = [
            [mb_strtoupper("Tableau de revue des libellés — {$b['sp_texte']} — Exercice {$this->annee}")],
            [''],
        ];
        $this->fusions[] = 'A' . self::TITRE . ':G' . self::TITRE;

        $entete = [
            'Sous-programme'                  => $b['sp_texte'],
            'Programme de rattachement'       => $b['programme'] ?? 'Non rattaché',
            "Programme budgétaire de l'EP"    => $b['programme_ep'] ?? 'Non rattaché',
            'Responsable'                     => $b['responsable'] ?? 'Non désigné',
            'Objectif'                        => $b['sp']->objectif ?? '—',
            'Indicateur(s) du sous-programme' => $this->liste($b['indicateurs'] ?? [])['texte'],
        ];

        $rang = self::ENTETE;
        foreach ($entete as $libelle => $valeur) {
            $lignes[] = [$libelle, $valeur];
            $this->fusions[] = "B{$rang}:G{$rang}";
            if (in_array($valeur, ['Non rattaché', 'Non désigné', 'Aucun'], true)) {
                $this->manquants[] = "B{$rang}";
            }
            $this->besoin($rang, $valeur, 150);
            $rang++;
        }

        $lignes[] = [$this->compteurs()];
        $this->fusions[] = 'A' . self::COMPTEURS . ':G' . self::COMPTEURS;
        $lignes[] = [''];
        $lignes[] = self::COLONNES;

        $rang = self::PREMIERE_LIGNE;
        foreach ($b['lignes'] as $row) {
            $cellule = array_fill(0, 7, '');

            if ($action = $row['action']) {
                $cellule[0] = $this->texte($action);
                $this->vertical('A', $rang, $action['rowspan'], $cellule[0]);
                $this->marquer("A{$rang}", (bool) $action['alertes'], gras: true);
            }

            if ($activite = $row['activite']) {
                $cellule[1] = $this->texte($activite);
                $extrants = $this->liste($activite['extrants']);
                $indicateurs = $this->liste($activite['indicateurs']);
                $cellule[2] = $extrants['texte'];
                $cellule[3] = $indicateurs['texte'];

                $this->vertical('B', $rang, $activite['rowspan'], $cellule[1]);
                $this->vertical('C', $rang, $activite['rowspan'], $cellule[2]);
                $this->vertical('D', $rang, $activite['rowspan'], $cellule[3]);
                $this->marquer("B{$rang}", (bool) $activite['alertes'], gras: true);
                $this->marquer("C{$rang}", $extrants['alerte']);
                $this->marquer("D{$rang}", $indicateurs['alerte']);
                if ($extrants['vide']) $this->manquants[] = "C{$rang}";
                if ($indicateurs['vide']) $this->manquants[] = "D{$rang}";
            }

            if ($message = $row['message']) {
                // Le constat reprend les colonnes restantes : 6 → B..G, 3 → E..G (comme le PDF).
                $colonne = $row['message_colspan'] >= 6 ? 'B' : 'E';
                $cellule[ord($colonne) - 65] = $message;
                $this->fusions[] = "{$colonne}{$rang}:G{$rang}";
                $this->messages[] = "{$colonne}{$rang}";
                $this->besoin($rang, $message, $colonne === 'B' ? 158 : 102);
            } else {
                if ($tache = $row['tache']) {
                    $cellule[4] = $this->texte($tache);
                    $this->vertical('E', $rang, $tache['rowspan'], $cellule[4]);
                    $this->marquer("E{$rang}", (bool) $tache['alertes']);
                }

                $cellule[5] = $row['ligne']['sous_tache'] ?? '—';
                $nomenclature = $row['ligne']['nomenclature'] ?? null;
                $cellule[6] = $nomenclature ?? 'Non imputée';
                if (!$nomenclature) $this->manquants[] = "G{$rang}";

                $this->besoin($rang, $cellule[5], self::LARGEURS['F']);
                $this->besoin($rang, $cellule[6], self::LARGEURS['G']);
            }

            $lignes[] = $cellule;
            $this->derniereLigne = $rang;
            $rang++;
        }

        if (!$b['lignes']) {
            $lignes[] = ['Aucune action rattachée à ce sous-programme.'];
            $this->fusions[] = 'A' . self::PREMIERE_LIGNE . ':G' . self::PREMIERE_LIGNE;
            $this->messages[] = 'A' . self::PREMIERE_LIGNE;
            $rang++;
        }

        // ── Observations de ce sous-programme, en bas de sa feuille ─────────────
        $lignes[] = [''];
        $this->titreObservations = ++$rang;
        $lignes[] = ['Observations sur les libellés'];
        $this->fusions[] = "A{$rang}:G{$rang}";

        $this->entetesObservations = ++$rang;
        $lignes[] = ['Niveau', 'Libellé', '', '', 'Observation'];
        $this->fusions[] = "B{$rang}:D{$rang}";
        $this->fusions[] = "E{$rang}:G{$rang}";

        foreach ($b['observations'] as $o) {
            $rang++;
            $lignes[] = [$o['niveau'], $o['libelle'], '', '', $o['constat']];
            $this->fusions[] = "B{$rang}:D{$rang}";
            $this->fusions[] = "E{$rang}:G{$rang}";
            $this->besoin($rang, $o['libelle'], 82);
            $this->besoin($rang, $o['constat'], 76);
            $this->derniereObservation = $rang;
        }

        return $lignes;
    }

    public function columnWidths(): array
    {
        return self::LARGEURS;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            self::TITRE => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A5F']],
            ],
            self::COMPTEURS => ['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']]],
            self::EN_TETES => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F3F4F6']],
            ],
        ];

        if ($this->titreObservations) {
            $styles[$this->titreObservations] = ['font' => ['bold' => true]];
            $styles[$this->entetesObservations] = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F3F4F6']],
            ];
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->fusions as $plage) {
                    $sheet->mergeCells($plage);
                }

                foreach ($this->alertes as $plage) {
                    $sheet->getStyle($plage)->applyFromArray(
                        ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FEFCE8']]]);
                }
                foreach ($this->manquants as $plage) {
                    $sheet->getStyle($plage)->applyFromArray(
                        ['font' => ['italic' => true, 'color' => ['rgb' => 'DC2626']]]);
                }
                foreach ($this->messages as $plage) {
                    $sheet->getStyle($plage)->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => '9CA3AF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
                foreach ($this->gras as $plage) {
                    $sheet->getStyle($plage)->getFont()->setBold(true);
                }

                $fin = max($this->derniereLigne, $this->derniereObservation);

                // Sans retour à la ligne, le texte des cellules fusionnées est coupé dans Excel
                $alignment = $sheet->getStyle("A1:G{$fin}")->getAlignment();
                $alignment->setVertical(Alignment::VERTICAL_TOP);
                $alignment->setWrapText(true);

                $sheet->getStyle('A' . self::ENTETE . ':A' . (self::ENTETE + 5))->getFont()->setBold(true);

                $bordure = ['borders' => ['allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ]]];
                $sheet->getStyle('A' . self::EN_TETES . ':G' . $this->derniereLigne)->applyFromArray($bordure);
                if ($this->derniereObservation) {
                    $sheet->getStyle("A{$this->entetesObservations}:G{$this->derniereObservation}")
                        ->applyFromArray($bordure);
                }

                $this->appliquerHauteurs($sheet);

                // Titre, en-tête du sous-programme et colonnes restent visibles au défilement
                $sheet->freezePane('A' . self::PREMIERE_LIGNE);
            },
        ];
    }

    /** Tout texte commençant par '=' est forcé en texte : jamais interprété comme formule. */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    // ────────────────────────────────────────────────────────────────

    protected function compteurs(): string
    {
        $c = $this->bloc['compteurs'];

        return sprintf(
            '%d action(s) · %d activité(s) · %d extrant(s) · %d indicateur(s) · %d tâche(s) · %d sous-tâche(s) · %d observation(s)',
            $c['actions'], $c['activites'], $c['extrants'], $c['indicateurs'],
            $c['taches'], $c['sous_taches'], $this->bloc['observations']->count()
        );
    }

    /** Libellé + marque ⚠ quand le contrôle de formulation a quelque chose à redire. */
    protected function texte(array $item): string
    {
        return ($item['texte'] ?? '') . (!empty($item['alertes']) ? ' ⚠' : '');
    }

    /** Liste à puces d'extrants ou d'indicateurs : texte, et de quoi signaler la cellule. */
    protected function liste(array $items): array
    {
        if (!$items) {
            return ['texte' => 'Aucun', 'alerte' => false, 'vide' => true];
        }

        $alerte = false;
        $textes = array_map(function ($i) use (&$alerte) {
            if (!empty($i['alertes'])) $alerte = true;
            return '• ' . $this->texte($i);
        }, $items);

        return ['texte' => implode("\n", $textes), 'alerte' => $alerte, 'vide' => false];
    }

    protected function marquer(string $plage, bool $alerte, bool $gras = false): void
    {
        if ($alerte) $this->alertes[] = $plage;
        if ($gras) $this->gras[] = $plage;
    }

    /** Fusion verticale d'une cellule parente sur les lignes de son groupe. */
    protected function vertical(string $colonne, int $debut, int $rowspan, string $texte): void
    {
        $fin = $debut + max($rowspan, 1) - 1;
        if ($fin > $debut) {
            $this->fusions[] = "{$colonne}{$debut}:{$colonne}{$fin}";
        }
        $this->groupes[] = [$debut, $fin, $this->lignes($texte, self::LARGEURS[$colonne])];
    }

    protected function besoin(int $rang, string $texte, int $largeur): void
    {
        $this->besoins[$rang] = max($this->besoins[$rang] ?? 1, $this->lignes($texte, $largeur));
    }

    /** Nombre de lignes d'affichage d'un texte dans une colonne de $largeur caractères. */
    protected function lignes(string $texte, int $largeur): int
    {
        $total = 0;
        foreach (explode("\n", $texte) as $segment) {
            $total += max(1, (int) ceil(mb_strlen($segment) / $largeur));
        }

        return $total;
    }

    /**
     * Excel ne calcule pas la hauteur des lignes fusionnées : on fixe celle du début du groupe pour
     * absorber son texte, moins ce que les autres lignes du groupe offrent déjà.
     */
    protected function appliquerHauteurs(Worksheet $sheet): void
    {
        $besoins = $this->besoins;
        foreach ($this->groupes as [$debut, $fin, $lignes]) {
            $besoins[$debut] = max($besoins[$debut] ?? 1, $lignes - max($fin - $debut, 0));
        }

        foreach ($besoins as $rang => $lignes) {
            if ($lignes > 1) {
                $sheet->getRowDimension($rang)->setRowHeight(min($lignes, 12) * 13 + 2);
            }
        }
    }
}
