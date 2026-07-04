<?php

namespace App\Filament\Budget\Resources\DecisionPrevisionnelleResource\Pages;

use App\Filament\Budget\Resources\DecisionPrevisionnelleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListDecisionsPrevisionnelles extends ListRecords
{
    protected static string $resource = DecisionPrevisionnelleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouvelle Décision')
            ->icon('heroicon-o-plus'),];
    }
}
