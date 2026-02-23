<?php

namespace App\Exports;

use App\Models\OrdonnancePaiement;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class OrdonnancesPaiementExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    use Exportable;

    protected $typeOrdonnance;
    protected $dateDebut;
    protected $dateFin;
    protected $mois;
    protected $annee;

    public function __construct($typeOrdonnance = null, $dateDebut = null, $dateFin = null, $mois = null, $annee = null)
    {
        $this->typeOrdonnance = $typeOrdonnance;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->mois = $mois;
        $this->annee = $annee;
    }

    /**
     * Query pour récupérer les données
     */
    public function query()
    {
        $query = OrdonnancePaiement::query()
            ->with(['engagement', 'beneficiaire', 'exercice'])
            ->whereIn('type_ordonnance', ['standard', 'impot']);

        // Filtre par type
        if ($this->typeOrdonnance) {
            $query->where('type_ordonnance', $this->typeOrdonnance);
        }

        // Filtre par mois et année
        if ($this->mois && $this->annee) {
            $query->whereMonth('date_emission', $this->mois)
                ->whereYear('date_emission', $this->annee);
        }
        // Ou filtre par période
        elseif ($this->dateDebut && $this->dateFin) {
            $query->whereBetween('date_emission', [$this->dateDebut, $this->dateFin]);
        }

        return $query->orderBy('date_emission', 'desc')
            ->orderBy('numero', 'asc');
    }

    /**
     * En-têtes des colonnes
     */
    public function headings(): array
    {
        return [
            'N° OP',
            'Type',
            'Date Émission',
            'N° Engagement',
            'Objet',
            'Bénéficiaire',
            'Montant Brut (FCFA)',
            'À Précompter (FCFA)',
            'Montant Net (FCFA)',
            'Statut',
            'Date Paiement',
            'Exercice',
        ];
    }

    /**
     * Mapper les données
     */
    public function map($ordonnance): array
    {
        return [
            $ordonnance->numero,
            $ordonnance->type_ordonnance === 'standard' ? 'Standard' : 'Impôt',
            $ordonnance->date_emission ? \Carbon\Carbon::parse($ordonnance->date_emission)->format('d/m/Y') : '',
            $ordonnance->engagement?->numero ?? '',
            $ordonnance->objet,
            $this->getBeneficiaireNom($ordonnance),
            number_format($ordonnance->montant_brut, 0, ',', ' '),
            number_format($ordonnance->montant_impot, 0, ',', ' '),
            number_format($ordonnance->montant_net, 0, ',', ' '),
            $this->getStatutLabel($ordonnance->statut),
            $ordonnance->date_paiement ? \Carbon\Carbon::parse($ordonnance->date_paiement)->format('d/m/Y') : '',
            $ordonnance->exercice?->annee ?? '',
        ];
    }

    /**
     * Obtenir le nom du bénéficiaire
     */
    protected function getBeneficiaireNom($ordonnance): string
    {
        if ($ordonnance->type_ordonnance === 'impot') {
            return 'TRÉSOR PUBLIC';
        }

        if (!$ordonnance->beneficiaire) {
            return 'N/A';
        }

        return $ordonnance->beneficiaire->raison_sociale
            ?? $ordonnance->beneficiaire->nom_complet
            ?? $ordonnance->beneficiaire->name
            ?? 'N/A';
    }

    /**
     * Obtenir le libellé du statut
     */
    protected function getStatutLabel($statut): string
    {
        return match ($statut) {
            'brouillon' => 'Brouillon',
            'emise' => 'Émise',
            'visee' => 'Visée',
            'payee' => 'Payée',
            'annulee' => 'Annulée',
            default => ucfirst($statut),
        };
    }

    /**
     * Styles du tableau
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style de l'en-tête
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
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
     * Largeur des colonnes
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,  // N° OP
            'B' => 12,  // Type
            'C' => 15,  // Date Émission
            'D' => 18,  // N° Engagement
            'E' => 40,  // Objet
            'F' => 30,  // Bénéficiaire
            'G' => 18,  // Montant Brut
            'H' => 18,  // À Précompter
            'I' => 18,  // Montant Net
            'J' => 12,  // Statut
            'K' => 15,  // Date Paiement
            'L' => 12,  // Exercice
        ];
    }

    /**
     * Titre de la feuille
     */
    public function title(): string
    {
        if ($this->typeOrdonnance === 'standard') {
            return 'OP Standard';
        } elseif ($this->typeOrdonnance === 'impot') {
            return 'OP Impôt';
        }
        return 'Ordonnances Paiement';
    }
}
