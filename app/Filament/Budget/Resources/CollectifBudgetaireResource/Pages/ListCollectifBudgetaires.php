<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\Pages;

use App\Filament\Budget\Resources\CollectifBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCollectifBudgetaires extends ListRecords
{
    protected static string $resource = CollectifBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
