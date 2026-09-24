<?php

namespace App\Filament\SuiviEvaluation\Resources\RapportAnnuelPerformanceResource\Pages;

use App\Filament\SuiviEvaluation\Resources\RapportAnnuelPerformanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRapportAnnuelPerformances extends ListRecords
{
    protected static string $resource = RapportAnnuelPerformanceResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
