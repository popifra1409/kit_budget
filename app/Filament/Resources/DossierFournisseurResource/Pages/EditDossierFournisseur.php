<?php

namespace App\Filament\Resources\DossierFournisseurResource\Pages;

use App\Filament\Resources\DossierFournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDossierFournisseur extends EditRecord
{
    protected static string $resource = DossierFournisseurResource::class;

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
