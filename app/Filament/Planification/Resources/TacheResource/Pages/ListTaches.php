<?php

namespace App\Filament\Planification\Resources\TacheResource\Pages;

use App\Filament\Planification\Resources\TacheResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaches extends ListRecords
{
    protected static string $resource = TacheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle Tache')
                ->icon('heroicon-o-plus'),
        ];
    }
}
