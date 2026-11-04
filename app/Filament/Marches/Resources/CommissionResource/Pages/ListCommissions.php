<?php

namespace App\Filament\Marches\Resources\CommissionResource\Pages;

use App\Filament\Marches\Resources\CommissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
