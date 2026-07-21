<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\Pages;

use App\Filament\Budget\Resources\CollectifBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCollectifBudgetaire extends ViewRecord
{
    protected static string $resource = CollectifBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
