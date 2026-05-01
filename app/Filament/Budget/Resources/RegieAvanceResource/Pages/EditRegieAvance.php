<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\Pages;

use App\Filament\Budget\Resources\RegieAvanceResource;
use Filament\Resources\Pages\EditRecord;

class EditRegieAvance extends EditRecord
{
    protected static string $resource = RegieAvanceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
