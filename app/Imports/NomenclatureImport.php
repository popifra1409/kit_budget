<?php

namespace App\Imports;

use App\Models\NomenclatureBudgetaire;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;

class NomenclatureImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
{
    private $dateDebutValidite;

    public function __construct($dateDebutValidite = null)
    {
        $this->dateDebutValidite = $dateDebutValidite ?? Carbon::now()->startOfYear();
    }

    /**
     * Transformation de chaque ligne en modèle
     */
    public function model(array $row)
    {
        // Ignorer les lignes vides
        if (empty($row['code']) || empty($row['libelle'])) {
            return null;
        }

        // Déterminer la classe à partir du code
        $code = trim($row['code']);
        $premierChiffre = substr($code, 0, 1);

        // Classe 6 = dépenses, Classe 7 = recettes
        $classe = $premierChiffre;
        $type = $premierChiffre === '6' ? 'depense' : 'recette';

        // Déterminer le niveau hiérarchique selon la longueur du code
        $niveau = $this->determinerNiveau($code);

        // Trouver le parent
        $parentId = $this->trouverParent($code);

        return new NomenclatureBudgetaire([
            'code' => $code,
            'libelle' => trim($row['libelle']),
            'classe' => $classe,
            'type' => $type,
            'niveau' => $niveau,
            'parent_id' => $parentId,
            'date_debut_validite' => $this->dateDebutValidite,
            'date_fin_validite' => null,
            'version' => 1,
            'ordre' => $row['ordre'] ?? 0,
            'actif' => true,
        ]);
    }

    /**
     * Déterminer le niveau hiérarchique selon la longueur du code
     */
    private function determinerNiveau($code): string
    {
        $longueur = strlen($code);

        if ($longueur <= 1) {
            return 'classe';
        } elseif ($longueur <= 2) {
            return 'compte';
        } elseif ($longueur <= 4) {
            return 'sous_compte';
        } else {
            return 'ligne';
        }
    }

    /**
     * Trouver le parent dans la hiérarchie
     */
    private function trouverParent($code)
    {
        if (strlen($code) <= 1) {
            return null; // Pas de parent pour la classe
        }

        // Le parent est le code avec un caractère en moins
        $codeParent = substr($code, 0, -1);

        // Pour les codes de 3-4 caractères, le parent peut être à 2 caractères
        if (strlen($code) >= 3 && strlen($codeParent) > 2) {
            $codeParent = substr($code, 0, 2);
        }

        $parent = NomenclatureBudgetaire::where('code', $codeParent)
            ->whereNull('date_fin_validite')
            ->first();

        return $parent ? $parent->id : null;
    }

    /**
     * Règles de validation
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20',
            'libelle' => 'required|string|max:255',
        ];
    }

    /**
     * Nombre de lignes à insérer par batch
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Taille du chunk de lecture
     */
    public function chunkSize(): int
    {
        return 100;
    }
}
