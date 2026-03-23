<?php

namespace App\Filament\Budget\Resources\MemoireDepenseResource\Pages;

use App\Filament\Budget\Resources\MemoireDepenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditMemoireDepense extends EditRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('generer_pdf')
                ->label('Générer PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn() => route('memoire-depense.pdf', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mémoire mis à jour')
            ->body('Le mémoire de dépense a été mis à jour avec succès.');
    }

    protected function afterSave(): void
    {
        // Recalculer les totaux après sauvegarde
        $this->record->fresh();
        $this->record->calculerTotaux();
        $this->record->saveQuietly();
    }
}
