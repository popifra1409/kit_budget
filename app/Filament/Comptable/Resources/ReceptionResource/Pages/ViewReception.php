<?php

namespace App\Filament\Comptable\Resources\ReceptionResource\Pages;

use App\Filament\Comptable\Resources\ReceptionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewReception extends ViewRecord
{
    protected static string $resource = ReceptionResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn() => $this->record->statut === 'brouillon'),
        ];
    }
}
