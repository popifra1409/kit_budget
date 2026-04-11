<?php

namespace App\Filament\Comptable\Resources\ReceptionResource\Pages;

use App\Filament\Comptable\Resources\ReceptionResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditReception extends EditRecord
{
    protected static string $resource = ReceptionResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
