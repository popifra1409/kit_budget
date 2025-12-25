<?php

namespace App\Filament\Resources\VirementBudgetaireResource\Pages;

use App\Filament\Resources\VirementBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVirementBudgetaires extends ListRecords
{
    protected static string $resource = VirementBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau virement'),
        ];
    }
}
