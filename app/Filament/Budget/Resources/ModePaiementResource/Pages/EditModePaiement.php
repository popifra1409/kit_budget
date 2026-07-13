<?php

namespace App\Filament\Budget\Resources\ModePaiementResource\Pages;

use App\Filament\Budget\Resources\ModePaiementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditModePaiement extends EditRecord
{
    protected static string $resource = ModePaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
