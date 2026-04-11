<?php

namespace App\Filament\Comptable\Resources\ArticleResource\Pages;

use App\Filament\Comptable\Resources\ArticleResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
