<?php

namespace App\Filament\Comptable\Resources\ExpressionBesoinResource\Pages;

use App\Filament\Comptable\Resources\ExpressionBesoinResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewExpressionBesoin extends ViewRecord
{
    protected static string $resource = ExpressionBesoinResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn() => $this->record->estModifiable()),
        ];
    }
}
