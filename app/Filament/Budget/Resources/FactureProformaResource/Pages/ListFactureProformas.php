<?php

namespace App\Filament\Budget\Resources\FactureProformaResource\Pages;

use App\Filament\Budget\Resources\FactureProformaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFactureProformas extends ListRecords
{
    protected static string $resource = FactureProformaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle facture proforma'),
        ];
    }
}
