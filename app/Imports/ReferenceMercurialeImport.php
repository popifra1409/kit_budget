<?php

namespace App\Imports;

use App\Models\ReferenceMercuriale;
use App\Models\Exercice;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Validators\Failure;

class ReferenceMercurialeImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsEmptyRows,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    protected $exerciceId;
    protected $failures = [];

    public function __construct(?int $exerciceId = null)
    {
        $this->exerciceId = $exerciceId ?? Exercice::getActif()?->id;
    }

    /**
     * Convertir chaque ligne en modèle
     */
    public function model(array $row)
    {
        // Nettoyer les données
        $codeReference = trim($row['code_reference'] ?? $row['code'] ?? '');
        $designation = trim($row['designation'] ?? '');
        $unite = trim($row['unite'] ?? 'pièce');
        $prixReference = $this->nettoyerPrix($row['prix_reference'] ?? $row['prix'] ?? 0);
        $rubrique = trim($row['rubrique'] ?? '');
        $sousRubrique = trim($row['sous_rubrique'] ?? $row['sous_rubrique'] ?? '');

        // Vérifier si la référence existe déjà
        $existing = ReferenceMercuriale::where('code_reference', $codeReference)
            ->where('exercice_id', $this->exerciceId)
            ->first();

        if ($existing) {
            // Mettre à jour
            $existing->update([
                'designation' => $designation,
                'unite' => $unite,
                'prix_reference' => $prixReference,
                'rubrique' => $rubrique,
                'sous_rubrique' => $sousRubrique,
                'actif' => true,
            ]);
            return null;
        }

        // Créer nouvelle référence
        return new ReferenceMercuriale([
            'exercice_id' => $this->exerciceId,
            'code_reference' => $codeReference,
            'designation' => $designation,
            'unite' => $unite,
            'prix_reference' => $prixReference,
            'rubrique' => $rubrique,
            'sous_rubrique' => $sousRubrique,
            'actif' => true,
        ]);
    }

    /**
     * Validation des données
     */
    public function rules(): array
    {
        return [
            'code_reference' => 'required|string|max:255',
            'code' => 'required_without:code_reference|string|max:255',
            'designation' => 'required|string|max:255',
            'unite' => 'nullable|string|max:255',
            'prix_reference' => 'required|numeric|min:0',
            'prix' => 'required_without:prix_reference|numeric|min:0',
        ];
    }

    /**
     * Messages de validation personnalisés
     */
    public function customValidationMessages()
    {
        return [
            'code_reference.required' => 'Le code de référence est obligatoire',
            'designation.required' => 'La désignation est obligatoire',
            'prix_reference.required' => 'Le prix de référence est obligatoire',
            'prix_reference.numeric' => 'Le prix doit être un nombre',
        ];
    }

    /**
     * Gérer les échecs de validation
     */
    public function onFailure(Failure ...$failures)
    {
        $this->failures = array_merge($this->failures, $failures);
    }

    /**
     * Obtenir les échecs
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Insertion par lots pour améliorer les performances
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Lecture par chunks pour les gros fichiers
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Nettoyer le prix (enlever espaces, virgules, etc.)
     */
    protected function nettoyerPrix($prix): float
    {
        if (is_numeric($prix)) {
            return (float) $prix;
        }

        // Enlever les espaces et remplacer virgule par point
        $prix = str_replace([' ', ','], ['', '.'], trim($prix));

        return (float) $prix;
    }
}
