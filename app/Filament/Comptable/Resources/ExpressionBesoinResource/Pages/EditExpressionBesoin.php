<?php

namespace App\Filament\Comptable\Resources\ExpressionBesoinResource\Pages;

use App\Filament\Comptable\Resources\ExpressionBesoinResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditExpressionBesoin extends EditRecord
{
    protected static string $resource = ExpressionBesoinResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
