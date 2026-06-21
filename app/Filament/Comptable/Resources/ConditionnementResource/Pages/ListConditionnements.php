<?php

namespace App\Filament\Comptable\Resources\ConditionnementResource\Pages;

use App\Filament\Comptable\Resources\ConditionnementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConditionnements extends ListRecords
{
    protected static string $resource = ConditionnementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
