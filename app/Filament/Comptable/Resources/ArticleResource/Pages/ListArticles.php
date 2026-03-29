<?php

namespace App\Filament\Comptable\Resources\ArticleResource\Pages;

use App\Filament\Comptable\Resources\ArticleResource;
use Filament\Resources\Pages\ListRecords;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;
    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
