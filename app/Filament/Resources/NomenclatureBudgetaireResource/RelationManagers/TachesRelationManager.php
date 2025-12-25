<?php

namespace App\Filament\Resources\NomenclatureBudgetaireResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TachesRelationManager extends RelationManager
{
    protected static string $relationship = 'taches';

    protected static ?string $title = 'Cadre Logique';

    protected static ?string $label = 'Tâche';

    protected static ?string $pluralLabel = 'Tâches liées';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('libelle')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('libelle')
            ->columns([
                Tables\Columns\TextColumn::make('activite.action.programme.code')
                    ->label('Programme')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->activite->action->programme->code . ' - ' .
                            \Str::limit($record->activite->action->programme->libelle, 30)
                    ),

                Tables\Columns\TextColumn::make('activite.action.code')
                    ->label('Action')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->activite->action->code . ' - ' .
                            \Str::limit($record->activite->action->libelle, 30)
                    ),

                Tables\Columns\TextColumn::make('activite.code')
                    ->label('Activité')
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->activite->code . ' - ' .
                            \Str::limit($record->activite->libelle, 30)
                    ),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Tâche')
                    ->searchable()
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('ae')
                    ->label('AE (FCFA)')
                    ->money('XAF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cp')
                    ->label('CP (FCFA)')
                    ->money('XAF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resultat_attendu')
                    ->label('Résultat attendu')
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('indicateur_resultat')
                    ->label('Indicateur')
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Pas de création depuis ici
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('voir_details')
                    ->label('Voir détails complets')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Détails : ' . $record->libelle)
                    ->modalContent(fn($record) => view('filament.modals.tache-details', [
                        'tache' => $record
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ])
            ->bulkActions([
                //
            ]);
    }
}
