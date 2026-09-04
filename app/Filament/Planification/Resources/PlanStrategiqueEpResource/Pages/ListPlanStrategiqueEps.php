<?php

namespace App\Filament\Planification\Resources\PlanStrategiqueEpResource\Pages;

use App\Filament\Planification\Resources\PlanStrategiqueEpResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlanStrategiqueEps extends ListRecords
{
    protected static string $resource = PlanStrategiqueEpResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()
            ->label('Nouveau Plan')
            ->icon('heroicon-o-plus'),];
    }
}
