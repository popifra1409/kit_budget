<?php

namespace App\Filament\Marches\Resources\TypeProcedureResource\Pages;

use App\Filament\Marches\Resources\TypeProcedureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTypeProcedures extends ListRecords
{
    protected static string $resource = TypeProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
