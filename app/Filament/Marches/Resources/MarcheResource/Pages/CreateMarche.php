<?php

namespace App\Filament\Marches\Resources\MarcheResource\Pages;

use App\Filament\Marches\Resources\MarcheResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * La référence (numero), le statut brouillon et le classement par le seuil sont posés
 * par les hooks du modèle : la fiche n'a donc rien à préparer d'autre que le
 * contingentement du classement manuel.
 */
class CreateMarche extends CreateRecord
{
    use GereLeClassementDuMarche;

    protected static string $resource = MarcheResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normaliserClassement($data);
    }
}
