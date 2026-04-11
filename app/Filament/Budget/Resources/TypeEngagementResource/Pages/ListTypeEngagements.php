<?php

namespace App\Filament\Budget\Resources\TypeEngagementResource\Pages;

use App\Filament\Budget\Resources\TypeEngagementResource;
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
