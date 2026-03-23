<?php

namespace App\Filament\Budget\Resources\ReferenceMercurialeResource\Pages;

use App\Filament\Budget\Resources\ReferenceMercurialeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReferenceMercuriale extends EditRecord
{
    protected static string $resource = ReferenceMercurialeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
