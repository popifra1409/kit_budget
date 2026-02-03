<?php

namespace App\Imports;

use App\Models\Service;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ServicesImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnError,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    use SkipsErrors, SkipsFailures;

    private int $imported = 0;
    private int $updated = 0;

    public function model(array $row)
    {
        // Vérifier si le service existe déjà
        $service = Service::where('code', $row['code'])->first();

        if ($service) {
            // Mise à jour
            $service->update([
                'nom' => $row['nom'],
                'description' => $row['description'] ?? null,
                'responsable' => $row['responsable'] ?? null,
                'email' => $row['email'] ?? null,
                'telephone' => $row['telephone'] ?? null,
                'batiment' => $row['batiment'] ?? null,
                'bureau' => $row['bureau'] ?? null,
                'actif' => isset($row['actif']) ? (bool) $row['actif'] : true,
            ]);

            $this->updated++;
            return null;
        }

        // Création
        $this->imported++;

        return new Service([
            'code' => $row['code'],
            'nom' => $row['nom'],
            'description' => $row['description'] ?? null,
            'responsable' => $row['responsable'] ?? null,
            'email' => $row['email'] ?? null,
            'telephone' => $row['telephone'] ?? null,
            'batiment' => $row['batiment'] ?? null,
            'bureau' => $row['bureau'] ?? null,
            'actif' => isset($row['actif']) ? (bool) $row['actif'] : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'code.required' => 'Le code est obligatoire',
            'code.unique' => 'Ce code existe déjà',
            'nom.required' => 'Le nom est obligatoire',
            'email.email' => 'L\'email doit être valide',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getImported(): int
    {
        return $this->imported;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }
}
