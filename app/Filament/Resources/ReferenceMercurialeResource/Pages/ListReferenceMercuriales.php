<?php

namespace App\Filament\Resources\ReferenceMercurialeResource\Pages;

use App\Filament\Resources\ReferenceMercurialeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReferenceMercuriales extends ListRecords
{
    protected static string $resource = ReferenceMercurialeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
