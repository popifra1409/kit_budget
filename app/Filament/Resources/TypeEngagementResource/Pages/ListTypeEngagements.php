<?php

namespace App\Filament\Resources\TypeEngagementResource\Pages;

use App\Filament\Resources\TypeEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTypeEngagements extends ListRecords
{
    protected static string $resource = TypeEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
