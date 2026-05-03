<?php

namespace App\Filament\Budget\Resources\AchatDirectResource\Pages;
use App\Filament\Budget\Resources\AchatDirectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAchatsDirects extends ListRecords
{
    protected static string $resource = AchatDirectResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouvel achat direct')];
    }
}