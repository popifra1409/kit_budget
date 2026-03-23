<?php

namespace App\Filament\Budget\Resources\PrevisionRecetteResource\Pages;

use App\Filament\Budget\Resources\PrevisionRecetteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrevisionRecettes extends ListRecords
{
    protected static string $resource = PrevisionRecetteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
