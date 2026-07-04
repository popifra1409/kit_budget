<?php

namespace App\Filament\Budget\Resources\BonCommandeRegieResource\Pages;

use App\Filament\Budget\Resources\BonCommandeRegieResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBonsCommandeRegies extends ListRecords
{
    protected static string $resource = BonCommandeRegieResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouveau BCR/BCM')
            ->icon('heroicon-o-plus'),];
    }
}
