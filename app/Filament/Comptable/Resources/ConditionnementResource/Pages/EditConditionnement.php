<?php

namespace App\Filament\Comptable\Resources\ConditionnementResource\Pages;

use App\Filament\Comptable\Resources\ConditionnementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConditionnement extends EditRecord
{
    protected static string $resource = ConditionnementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
