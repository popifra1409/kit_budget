<?php

namespace App\Filament\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Resources\NomenclatureBudgetaireResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNomenclatureBudgetaire extends CreateRecord
{
    protected static string $resource = NomenclatureBudgetaireResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['version'] = 1;
        $data['modifie_par'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
