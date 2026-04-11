<?php

namespace App\Filament\Comptable\Resources\ReceptionResource\Pages;

use App\Filament\Comptable\Resources\ReceptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReception extends CreateRecord
{
    protected static string $resource = ReceptionResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
