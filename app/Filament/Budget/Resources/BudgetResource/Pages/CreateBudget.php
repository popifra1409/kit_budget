<?php

namespace App\Filament\Budget\Resources\BudgetResource\Pages;

use App\Filament\Budget\Resources\BudgetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBudget extends CreateRecord
{
    protected static string $resource = BudgetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
