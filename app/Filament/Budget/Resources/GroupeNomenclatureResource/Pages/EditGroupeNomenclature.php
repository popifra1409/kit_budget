<?php

namespace App\Filament\Budget\Resources\GroupeNomenclatureResource\Pages;

use App\Filament\Budget\Resources\GroupeNomenclatureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupeNomenclature extends EditRecord
{
    protected static string $resource = GroupeNomenclatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->estSupprimable()),
        ];
    }
}
