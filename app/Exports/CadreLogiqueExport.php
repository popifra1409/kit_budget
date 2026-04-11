<?php

namespace App\Exports;

use App\Models\Programme;
use App\Models\Tache;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CadreLogiqueExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $programmeId;
    protected $annee;

    public function __construct($programmeId = null, $annee = null)
    {
        $this->programmeId = $programmeId;
        $this->annee = $annee ?? now()->year;
    }

    /**
     * Récupérer les données
     */
    public function collection()
    {
        $query = Tache::with([
            'activite.action.programme.objectifsPrincipaux',
            'activite.action.objectifsSpecifiques',
            'nomenclature'
        ]);

        // Filtrer par programme si spécifié
        if ($this->programmeId) {
            $query->whereHas('activite.action.programme', function ($q) {
                $q->where('id', $this->programmeId);
            });
        }

        // Filtrer par année
        $query->whereHas('activite.action.programme', function ($q) {
            $q->where('annee', $this->annee);
        });

        $taches = $query->orderBy('id')->get();

        return $taches->map(function ($tache) {
            $activite = $tache->activite;
            $action = $activite->action;
            $programme = $action->programme;

            return [
                'programme_code' => $programme->code,
                'programme_libelle' => $programme->libelle,
                'objectif_principal' => $programme->objectifsPrincipaux->first()?->libelle ?? '',
                'action_code' => $action->code,
                'action_libelle' => $action->libelle,
                'objectif_specifique' => $action->objectifsSpecifiques->first()?->libelle ?? '',
                'activite_code' => $activite->code,
                'activite_libelle' => $activite->libelle,
                'tache_code' => $tache->code,
                'tache_libelle' => $tache->libelle,
                'nomenclature_code' => $tache->nomenclature->code ?? '',
                'nomenclature_libelle' => $tache->nomenclature->libelle ?? '',
                'delai' => $tache->delai ?? '',
                'guichet' => $tache->guichet ?? '',
                'service_responsable' => $tache->service?->nom ?? '',
                'ae' => $tache->ae,
                'cp' => $tache->cp,
                'resultat_attendu' => $tache->resultat_attendu ?? '',
                'indicateur_resultat' => $tache->indicateur_resultat ?? '',
            ];
        });
    }

    /**
     * En-têtes des colonnes
     */
    public function headings(): array
    {
        return [
            'Code Programme',
            'Programme',
            'Objectif Principal',
            'Code Action',
            'Action',
            'Objectif Spécifique',
            'Code Activité',
            'Activité',
            'Code Tâche',
            'Tâche',
            'Code Nomenclature',
            'Nomenclature Budgétaire',
            'Délai',
            'Guichet',
            'Service Responsable',
            'AE (FCFA)',
            'CP (FCFA)',
            'Résultat Attendu',
            'Indicateur de Résultat',
        ];
    }

    /**
     * Largeur des colonnes
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // Code Programme
            'B' => 40, // Programme
            'C' => 50, // Objectif Principal
            'D' => 12, // Code Action
            'E' => 40, // Action
            'F' => 50, // Objectif Spécifique
            'G' => 12, // Code Activité
            'H' => 40, // Activité
            'I' => 12, // Code Tâche
            'J' => 40, // Tâche
            'K' => 15, // Code Nomenclature
            'L' => 40, // Nomenclature
            'M' => 15, // Délai
            'N' => 20, // Guichet
            'O' => 25, // Service
            'P' => 18, // AE
            'Q' => 18, // CP
            'R' => 50, // Résultat
            'S' => 50, // Indicateur
        ];
    }

    /**
     * Styles
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style de l'en-tête
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Titre de la feuille
     */
    public function title(): string
    {
        return 'Cadre Logique ' . $this->annee;
    }
}
