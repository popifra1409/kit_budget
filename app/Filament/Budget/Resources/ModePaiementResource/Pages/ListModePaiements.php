<?php

namespace App\Filament\Budget\Resources\ModePaiementResource\Pages;

use App\Filament\Budget\Resources\ModePaiementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListModePaiements extends ListRecords
{
    protected static string $resource = ModePaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau mode de paiment')
                ->icon('heroicon-o-plus'),
        ];
    }
}
