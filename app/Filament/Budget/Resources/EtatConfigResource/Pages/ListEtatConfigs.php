<?php

namespace App\Filament\Budget\Resources\EtatConfigResource\Pages;

use App\Filament\Budget\Resources\EtatConfigResource;
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
