<?php

namespace App\Filament\Budget\Resources\FicheControleEngagementsResource\Pages;

use App\Filament\Budget\Resources\FicheControleEngagementsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFicheControleEngagements extends EditRecord
{
    protected static string $resource = FicheControleEngagementsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
