<?php

namespace App\Filament\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Resources\NomenclatureBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNomenclatureBudgetaires extends ListRecords
{
    protected static string $resource = NomenclatureBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('hierarchie')
                ->label('Vue Hiérarchique')
                ->icon('heroicon-o-queue-list')
                ->color('info')
                ->url(route('filament.admin.resources.nomenclature-budgetaires.hierarchie')),

            Actions\Action::make('import')
                ->label('Importer Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(route('filament.admin.resources.nomenclature-budgetaires.import')),

            Actions\CreateAction::make()
                ->label('Nouvelle nomenclature'),
        ];
    }
}
