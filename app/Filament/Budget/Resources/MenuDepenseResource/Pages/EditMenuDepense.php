<?php

namespace App\Filament\Budget\Resources\MenuDepenseResource\Pages;

use App\Filament\Budget\Resources\MenuDepenseResource;
use Filament\Resources\Pages\EditRecord;

class EditMenuDepense extends EditRecord
{
    protected static string $resource = MenuDepenseResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
