<?php

namespace App\Filament\Resources\EngagementResource\Pages;

use App\Filament\Resources\EngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEngagement extends EditRecord
{
    protected static string $resource = EngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
