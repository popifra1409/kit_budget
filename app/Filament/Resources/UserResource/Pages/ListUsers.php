<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Créer un utilisateur')
                ->icon('heroicon-o-plus')
                ->modalHeading('Créer un nouvel utilisateur')
                ->modalWidth('2xl'),
        ];
    }
}
