<?php

namespace App\Filament\Comptable\Resources\CategorieArticleResource\Pages;

use App\Filament\Comptable\Resources\CategorieArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategorieArticle extends EditRecord
{
    protected static string $resource = CategorieArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
