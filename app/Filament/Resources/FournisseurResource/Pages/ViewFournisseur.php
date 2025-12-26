<?php

namespace App\Filament\Resources\FournisseurResource\Pages;

use App\Filament\Resources\FournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFournisseur extends ViewRecord
{
    protected static string $resource = FournisseurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
