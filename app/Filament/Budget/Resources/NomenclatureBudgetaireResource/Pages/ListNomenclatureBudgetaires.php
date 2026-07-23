<?php

namespace App\Filament\Budget\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Budget\Resources\NomenclatureBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
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
                ->label('Nouvelle nomenclature')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Onglets par type — le regroupement par "groupe de nomenclature"
     * se fait ensuite à l'intérieur de chaque onglet via ->groups()
     * défini dans NomenclatureBudgetaireResource::table().
     */
    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Toutes')
                ->badge(fn() => \App\Models\NomenclatureBudgetaire::count()),

            'depense' => Tab::make('Dépenses')
                ->icon('heroicon-o-arrow-trending-down')
                ->badge(fn() => \App\Models\NomenclatureBudgetaire::where('type', 'depense')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn($query) => $query->where('type', 'depense')),

            'recette' => Tab::make('Recettes')
                ->icon('heroicon-o-arrow-trending-up')
                ->badge(fn() => \App\Models\NomenclatureBudgetaire::where('type', 'recette')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn($query) => $query->where('type', 'recette')),
        ];
    }
}
