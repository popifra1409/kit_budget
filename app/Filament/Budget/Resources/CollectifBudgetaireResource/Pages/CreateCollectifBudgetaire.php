<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\Pages;

use App\Filament\Budget\Resources\CollectifBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCollectifBudgetaire extends CreateRecord
{
    protected static string $resource = CollectifBudgetaireResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
