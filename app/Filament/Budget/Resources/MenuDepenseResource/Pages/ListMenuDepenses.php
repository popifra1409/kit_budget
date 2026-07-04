<?php

namespace App\Filament\Budget\Resources\MenuDepenseResource\Pages;

use App\Filament\Budget\Resources\MenuDepenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenuDepenses extends ListRecords
{
    protected static string $resource = MenuDepenseResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouveau Menu Dépense')
            ->icon('heroicon-o-plus'),];
    }
}
