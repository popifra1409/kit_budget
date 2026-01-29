<?php

namespace App\Filament\Resources\OrdonnancePaiementResource\Pages;

use App\Filament\Resources\OrdonnancePaiementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrdonnancePaiement extends EditRecord
{
    protected static string $resource = OrdonnancePaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
