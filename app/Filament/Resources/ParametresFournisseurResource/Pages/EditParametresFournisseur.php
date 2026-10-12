<?php

namespace App\Filament\Resources\ParametresFournisseurResource\Pages;

use App\Filament\Resources\ParametresFournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditParametresFournisseur extends EditRecord
{
    protected static string $resource = ParametresFournisseurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->disabled(fn($record) => $record->actif),

            Actions\ForceDeleteAction::make()
                ->requiresConfirmation(),

            Actions\RestoreAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Paramètres mis à jour')
            ->body('Les informations du fournisseur ont été mises à jour avec succès.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
