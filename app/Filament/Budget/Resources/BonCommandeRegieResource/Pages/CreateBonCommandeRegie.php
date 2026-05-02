<?php

namespace App\Filament\Budget\Resources\BonCommandeRegieResource\Pages;

use App\Filament\Budget\Resources\BonCommandeRegieResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBonCommandeRegie extends CreateRecord
{
    protected static string $resource = BonCommandeRegieResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
