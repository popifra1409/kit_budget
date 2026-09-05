<?php

namespace App\Filament\Planification\Resources\ActiviteResource\Pages;

use App\Filament\Planification\Resources\ActiviteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivites extends ListRecords
{
    protected static string $resource = ActiviteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle Activite')
                ->icon('heroicon-o-plus'),
        ];
    }
}
