<?php

namespace App\Filament\Budget\Resources\TypeEngagementResource\Pages;

use App\Filament\Budget\Resources\TypeEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTypeEngagement extends EditRecord
{
    protected static string $resource = TypeEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
