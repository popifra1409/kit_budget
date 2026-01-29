<?php

namespace App\Filament\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Resources\OrdonnancePaiementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrdonnancePaiement extends CreateRecord
{
    protected static string $resource = OrdonnancePaiementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Générer le numéro d'OP
        $data['numero'] = \App\Models\OrdonnancePaiement::genererNumero(
            $data['type_ordonnance']
        );

        // Définir le créateur
        $data['created_by'] = auth()->id();

        // Calculer mois et période depuis date_emission
        if (isset($data['date_emission'])) {
            $date = \Carbon\Carbon::parse($data['date_emission']);
            $data['mois_emission'] = $date->format('m');
            $data['periode'] = $date->format('m/Y');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
