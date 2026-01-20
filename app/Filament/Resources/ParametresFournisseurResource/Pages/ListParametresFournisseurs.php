<?php

namespace App\Filament\Resources\ParametresFournisseurResource\Pages;

use App\Filament\Resources\ParametresFournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParametresFournisseurs extends ListRecords
{
    protected static string $resource = ParametresFournisseurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau Paramétrage')
                ->icon('heroicon-o-plus'),
        ];
    }
}
