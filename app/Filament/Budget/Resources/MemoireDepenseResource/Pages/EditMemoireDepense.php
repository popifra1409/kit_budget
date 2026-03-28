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
            // ── Aperçu du mémoire ────────────────────────────────
            Actions\Action::make('apercu_memoire')
                ->label('Aperçu')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->outlined()
                ->modalHeading(fn() => 'Aperçu — ' . $this->record->numero)
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalContent(function () {
                    $this->record->load('lignes');
                    return view('filament.modals.apercu-memoire-depense', [
                        'memoire' => $this->record,
                    ]);
                }),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->statut === 'brouillon'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mémoire mis à jour')
            ->body("Le mémoire {$this->record->numero} a été sauvegardé.");
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->load('lignes');
        $this->record->calculerTotaux();
        $this->record->saveQuietly();
    }
}
