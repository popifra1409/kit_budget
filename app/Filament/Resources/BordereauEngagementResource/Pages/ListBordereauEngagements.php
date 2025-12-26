<?php

namespace App\Filament\Resources\BordereauEngagementResource\Pages;

use App\Filament\Resources\BordereauEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBordereauEngagements extends ListRecords
{
    protected static string $resource = BordereauEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
