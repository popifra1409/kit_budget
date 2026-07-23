<?php

namespace App\Filament\Budget\Resources\GroupeNomenclatureResource\Pages;

use App\Filament\Budget\Resources\GroupeNomenclatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupeNomenclatures extends ListRecords
{
    protected static string $resource = GroupeNomenclatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
