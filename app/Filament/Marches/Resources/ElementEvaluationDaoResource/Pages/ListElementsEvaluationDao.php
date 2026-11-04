<?php

namespace App\Filament\Marches\Resources\ElementEvaluationDaoResource\Pages;

use App\Filament\Marches\Resources\ElementEvaluationDaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListElementsEvaluationDao extends ListRecords
{
    protected static string $resource = ElementEvaluationDaoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
