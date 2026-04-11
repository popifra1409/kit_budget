<?php

namespace App\Filament\Comptable\Resources\ExpressionBesoinResource\Pages;

use App\Filament\Comptable\Resources\ExpressionBesoinResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpressionBesoin extends CreateRecord
{
    protected static string $resource = ExpressionBesoinResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
