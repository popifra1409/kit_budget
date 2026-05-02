<?php

namespace App\Filament\Budget\Resources\BonCommandeRegieResource\Pages;

use App\Filament\Budget\Resources\BonCommandeRegieResource;
use Filament\Resources\Pages\EditRecord;

class EditBonCommandeRegie extends EditRecord
{
    protected static string $resource = BonCommandeRegieResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
