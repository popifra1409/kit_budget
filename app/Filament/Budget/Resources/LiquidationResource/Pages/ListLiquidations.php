<?php

namespace App\Filament\Budget\Resources\LiquidationResource\Pages;

use App\Filament\Budget\Resources\LiquidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLiquidations extends ListRecords
{
    protected static string $resource = LiquidationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouvelle liquidation')];
    }
}
