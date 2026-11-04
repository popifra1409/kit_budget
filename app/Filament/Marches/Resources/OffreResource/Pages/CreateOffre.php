<?php

namespace App\Filament\Marches\Resources\OffreResource\Pages;

use App\Filament\Marches\Resources\OffreResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Le marché doit encore accueillir les offres et le soumissionnaire ne doit pas
 * avoir déjà déposé : VerifieRattachementOffre porte la règle sur le champ, avant
 * que le modèle refuse en SQL.
 */
class CreateOffre extends CreateRecord
{
    use VerifieRattachementOffre;

    protected static string $resource = OffreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->verifierRattachement($data);
    }

    /**
     * Retour au guichet : les offres d'un même DAO arrivent ensemble, et la ligne
     * qui vient d'être prise doit se voir dans la liste (avertissement de hors
     * délai, quittance) avant de saisir la suivante.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
