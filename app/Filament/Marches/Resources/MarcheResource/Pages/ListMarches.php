<?php

namespace App\Filament\Marches\Resources\MarcheResource\Pages;

use App\Filament\Marches\Resources\MarcheResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarches extends ListRecords
{
    protected static string $resource = MarcheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau marché')
                ->icon('heroicon-o-plus'),
        ];
    }
}
