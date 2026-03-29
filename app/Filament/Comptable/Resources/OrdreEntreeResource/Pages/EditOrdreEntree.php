<?php

namespace App\Filament\Comptable\Resources\OrdreEntreeResource\Pages;

use App\Filament\Comptable\Resources\OrdreEntreeResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditOrdreEntree extends EditRecord
{
    protected static string $resource = OrdreEntreeResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
