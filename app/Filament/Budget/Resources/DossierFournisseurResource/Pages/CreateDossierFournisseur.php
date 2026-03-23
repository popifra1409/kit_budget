<?php

namespace App\Filament\Budget\Resources\DossierFournisseurResource\Pages;

use App\Filament\Budget\Resources\DossierFournisseurResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDossierFournisseur extends CreateRecord
{
    protected static string $resource = DossierFournisseurResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Générer le numéro de dossier
        $data['numero_dossier'] = \App\Models\DossierFournisseur::genererNumeroDossier(
            $data['type_dossier']
        );

        // Définir le créateur
        $data['createur_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
