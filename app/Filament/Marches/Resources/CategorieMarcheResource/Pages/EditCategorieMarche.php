<?php

namespace App\Filament\Marches\Resources\CategorieMarcheResource\Pages;

use App\Filament\Marches\Resources\CategorieMarcheResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategorieMarche extends EditRecord
{
    protected static string $resource = CategorieMarcheResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
