<?php

namespace App\Filament\Budget\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Budget\Resources\NomenclatureBudgetaireResource;
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
                ->url(fn() => route('filament.budget.resources.nomenclature-budgetaires.hierarchie')),

            Actions\Action::make('import')
                ->label('Importer Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn() => route('filament.budget.resources.nomenclature-budgetaires.import')),

            Actions\CreateAction::make()
                ->label('Nouvelle nomenclature'),
        ];
    }
}
