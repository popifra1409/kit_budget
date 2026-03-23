<?php

namespace App\Filament\Budget\Resources\TypeDecisionResource\Pages;

use App\Filament\Budget\Resources\TypeDecisionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTypeDecisions extends ListRecords
{
    protected static string $resource = TypeDecisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
