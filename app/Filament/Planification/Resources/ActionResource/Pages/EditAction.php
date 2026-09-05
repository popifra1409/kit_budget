<?php

namespace App\Filament\Planification\Resources\ActionResource\Pages;

use App\Filament\Planification\Resources\ActionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAction extends EditRecord
{
    protected static string $resource = ActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
