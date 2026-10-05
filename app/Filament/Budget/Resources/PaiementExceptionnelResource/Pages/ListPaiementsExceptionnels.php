<?php

namespace App\Filament\Budget\Resources\PaiementExceptionnelResource\Pages;

use App\Filament\Budget\Resources\PaiementExceptionnelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaiementsExceptionnels extends ListRecords
{
    protected static string $resource = PaiementExceptionnelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouveau paiement exceptionnel')];
    }
}
