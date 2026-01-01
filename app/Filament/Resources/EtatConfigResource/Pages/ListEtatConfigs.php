<?php

namespace App\Filament\Resources\EtatConfigResource\Pages;

use App\Filament\Resources\EtatConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEtatConfigs extends ListRecords
{
    protected static string $resource = EtatConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
