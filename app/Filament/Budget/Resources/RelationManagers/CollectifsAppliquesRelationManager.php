<?php

namespace App\Filament\Budget\Resources\RelationManagers;

use App\Models\CollectifBudgetaire;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CollectifsAppliquesRelationManager extends RelationManager
{
    protected static string $relationship = 'collectifsAppliques';

    protected static ?string $recordTitleAttribute = 'numero';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Numéro')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('date_collectif')
                    ->label('Date du collectif')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_adoption')
                    ->label('Date d\'adoption')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'success' => 'adopte',
                        'danger'  => 'annule',
                        'secondary' => 'projet',
                    ]),
                Tables\Columns\TextColumn::make('mouvements_count')
                    ->label('Nb mouvements')
                    ->counts('mouvements')
                    ->badge(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(
                        fn(CollectifBudgetaire $record): string =>
                        route('filament.budget.resources.collectifs-budgetaires.view', $record)
                    ),
            ])
            ->bulkActions([])
            ->defaultSort('date_adoption', 'desc');
    }
}
