<?php

namespace App\Filament\Budget\Resources\FournisseurResource\Pages;

use App\Filament\Budget\Resources\FournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFournisseurs extends ListRecords
{
    protected static string $resource = FournisseurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau Fournisseur')
                ->icon('heroicon-o-plus'),
        ];
    }
}
