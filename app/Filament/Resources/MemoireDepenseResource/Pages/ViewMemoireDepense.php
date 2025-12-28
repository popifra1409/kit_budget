<?php

namespace App\Filament\Resources\MemoireDepenseResource\Pages;

use App\Filament\Resources\MemoireDepenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMemoireDepense extends ViewRecord
{
    protected static string $resource = MemoireDepenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('generer_pdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn() => route('memoire-depense.pdf', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
