<?php

namespace App\Filament\Comptable\Resources\FicheConsolidationBesoinResource\Pages;

use App\Filament\Comptable\Resources\FicheConsolidationBesoinResource;
use Filament\Resources\Pages\ListRecords;

class ListFicheConsolidationBesoins extends ListRecords
{
    protected static string $resource = FicheConsolidationBesoinResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
