<?php

namespace App\Filament\Comptable\Resources\ArticleResource\Pages;

use App\Filament\Comptable\Resources\ArticleResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewArticle extends ViewRecord
{
    protected static string $resource = ArticleResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
