<?php

namespace App\Filament\Resources\MemoireDepenseResource\Pages;

use App\Filament\Resources\MemoireDepenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMemoireDepenses extends ListRecords
{
    protected static string $resource = MemoireDepenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau Mémoire')
                ->icon('heroicon-o-plus'),
        ];
    }
}
