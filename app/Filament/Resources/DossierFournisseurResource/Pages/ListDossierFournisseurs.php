<?php

namespace App\Filament\Resources\DossierFournisseurResource\Pages;

use App\Filament\Resources\DossierFournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDossierFournisseurs extends ListRecords
{
    protected static string $resource = DossierFournisseurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
