<?php

namespace App\Filament\Budget\Resources\BordereauEngagementResource\Pages;

use App\Filament\Budget\Resources\BordereauEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBordereauEngagement extends EditRecord
{
    protected static string $resource = BordereauEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
