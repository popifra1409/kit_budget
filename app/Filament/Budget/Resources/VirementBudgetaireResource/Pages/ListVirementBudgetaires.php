<?php

namespace App\Filament\Budget\Resources\VirementBudgetaireResource\Pages;

use App\Filament\Budget\Resources\VirementBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVirementBudgetaires extends ListRecords
{
    protected static string $resource = VirementBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau Virement')
                ->icon('heroicon-o-plus'),
        ];
    }
}
