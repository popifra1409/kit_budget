<?php

namespace App\Filament\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Resources\NomenclatureBudgetaireResource;
use App\Models\NomenclatureBudgetaire;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class HierarchieNomenclatureBudgetaire extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = NomenclatureBudgetaireResource::class;

    protected static string $view = 'filament.resources.nomenclature-budgetaire-resource.pages.hierarchie-nomenclature-budgetaire';

    protected static ?string $title = 'Vue Hiérarchique';

    protected static ?string $navigationLabel = 'Hiérarchie';

    public $typeFiltre = 'depense'; // Par défaut on affiche les dépenses

    public function table(Table $table): Table
    {
        return $table
            ->query(
                NomenclatureBudgetaire::query()
                    ->whereNull('parent_id') // Seulement les racines
                    ->where('type', $this->typeFiltre)
                    ->whereNull('date_fin_validite')
                    ->orderBy('code')
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('classe')
                    ->label('Classe')
                    ->badge(),

                Tables\Columns\TextColumn::make('niveau')
                    ->label('Niveau')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'classe' => 'Classe',
                        'compte' => 'Compte',
                        'sous_compte' => 'Sous-compte',
                        'ligne' => 'Ligne',
                        default => $state,
                    }),
            ])
            ->contentGrid([
                'md' => 1,
            ])
            ->paginated(false);
    }

    public function getEnfants($parentId)
    {
        return NomenclatureBudgetaire::where('parent_id', $parentId)
            ->whereNull('date_fin_validite')
            ->orderBy('code')
            ->get();
    }

    public function changerType($type)
    {
        $this->typeFiltre = $type;
        $this->resetTable();
    }
}
