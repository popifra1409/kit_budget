<?php

namespace App\Filament\Budget\Resources\FournisseurResource\Pages;

use App\Filament\Budget\Resources\FournisseurResource;
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
