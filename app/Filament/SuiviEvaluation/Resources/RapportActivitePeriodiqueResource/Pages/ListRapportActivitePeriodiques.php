<?php

namespace App\Filament\SuiviEvaluation\Resources\RapportActivitePeriodiqueResource\Pages;

use App\Filament\SuiviEvaluation\Resources\RapportActivitePeriodiqueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRapportActivitePeriodiques extends ListRecords
{
    protected static string $resource = RapportActivitePeriodiqueResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
