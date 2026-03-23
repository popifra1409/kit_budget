<?php

namespace App\Filament\Budget\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Budget\Resources\NomenclatureBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNomenclatureBudgetaire extends EditRecord
{
    protected static string $resource = NomenclatureBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['modifie_par'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
