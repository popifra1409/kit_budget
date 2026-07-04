<?php

namespace App\Filament\Budget\Resources\ActionResource\Pages;

use App\Filament\Budget\Resources\ActionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActions extends ListRecords
{
    protected static string $resource = ActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('Nouvelle Action')
                ->icon('heroicon-o-plus'),
        ];
    }
}
