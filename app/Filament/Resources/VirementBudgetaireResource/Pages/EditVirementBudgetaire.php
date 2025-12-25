<?php

namespace App\Filament\Resources\VirementBudgetaireResource\Pages;

use App\Filament\Resources\VirementBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVirementBudgetaire extends EditRecord
{
    protected static string $resource = VirementBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn($record) => $record->statut === 'en_attente'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
