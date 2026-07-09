<?php

namespace App\Filament\Budget\Resources\ParametresStructureResource\Pages;

use App\Filament\Budget\Resources\ParametresStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParametresStructure extends EditRecord
{
    use \App\Filament\Budget\Concerns\HasAgentContext;
    protected static string $resource = ParametresStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
