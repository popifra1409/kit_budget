<?php

namespace App\Filament\Budget\Resources\GroupeNomenclatureResource\Pages;

use App\Filament\Budget\Resources\GroupeNomenclatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGroupeNomenclature extends ViewRecord
{
    protected static string $resource = GroupeNomenclatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
