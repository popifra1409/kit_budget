<?php

namespace App\Filament\Comptable\Resources\ExpressionBesoinResource\Pages;

use App\Filament\Comptable\Resources\ExpressionBesoinResource;
use Filament\Resources\Pages\ListRecords;

class ListExpressionBesoins extends ListRecords
{
    protected static string $resource = ExpressionBesoinResource::class;
    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
