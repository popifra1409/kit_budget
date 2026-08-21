<?php

namespace App\Filament\Budget\Resources\FactureProformaResource\Pages;

use App\Filament\Budget\Resources\FactureProformaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFactureProforma extends EditRecord
{
    protected static string $resource = FactureProformaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->estModifiable()),
        ];
    }
}
