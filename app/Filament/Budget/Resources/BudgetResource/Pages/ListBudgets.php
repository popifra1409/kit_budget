<?php

namespace App\Filament\Budget\Resources\BudgetResource\Pages;

use App\Filament\Budget\Resources\BudgetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgets extends ListRecords
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau Budget')
                ->icon('heroicon-o-plus'),
        ];
    }
}
