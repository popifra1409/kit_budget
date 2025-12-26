<?php

namespace App\Filament\Resources\EngagementResource\Pages;

use App\Filament\Resources\EngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEngagements extends ListRecords
{
    protected static string $resource = EngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
