<?php

namespace App\Filament\Planification\Resources\IndicateurResource\Pages;

use App\Filament\Planification\Resources\IndicateurResource;
use Filament\Resources\Pages\EditRecord;

class EditIndicateur extends EditRecord
{
    protected static string $resource = IndicateurResource::class;

    protected function getHeaderActions(): array
    {
        return []; // pas de suppression ici — se fait depuis le RelationManager parent
    }
}
