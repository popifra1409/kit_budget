<?php

namespace App\Filament\Programmation\Resources\CbmtExerciceResource\RelationManagers;

use App\Models\CbmtLigne;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Tableaux 9 & 10 — Ressources et Dépenses';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('nature')
                ->options(['ressource' => 'Ressource (Tableau 9)', 'depense' => 'Dépense (Tableau 10)'])
                ->live()->required(),

            Forms\Components\Select::make('titre')
                ->label('Titre')
                ->options(fn(Forms\Get $get) => $get('nature') === 'ressource'
                    ? CbmtLigne::TITRES_RESSOURCES
                    : CbmtLigne::TITRES_DEPENSES)
                ->required(),

            Forms\Components\TextInput::make('libelle_titre')->required()->columnSpanFull(),

            Forms\Components\TextInput::make('source')
                ->label('Source (A/B/C)')
                ->visible(fn(Forms\Get $get) => $get('nature') === 'ressource')
                ->maxLength(1),

            Forms\Components\Fieldset::make('Montants')
                ->schema([
                    Forms\Components\TextInput::make('montant_n_moins_1')->label('N-1')->numeric()->default(0),
                    Forms\Components\TextInput::make('montant_n')->label('N')->numeric()->default(0),
                    Forms\Components\TextInput::make('montant_n_plus_1')->label('N+1')->numeric()->default(0),
                    Forms\Components\TextInput::make('montant_n_plus_2')->label('N+2')->numeric()->default(0),
                    Forms\Components\TextInput::make('montant_n_plus_3')->label('N+3')->numeric()->default(0),
                ])->columns(5),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('nature')->colors(['success' => 'ressource', 'danger' => 'depense']),
                Tables\Columns\TextColumn::make('titre')->label('Titre'),
                Tables\Columns\TextColumn::make('libelle_titre')->wrap(),
                Tables\Columns\TextColumn::make('source'),
                Tables\Columns\TextColumn::make('montant_n_moins_1')->label('N-1')->numeric(0),
                Tables\Columns\TextColumn::make('montant_n')->label('N')->numeric(0),
                Tables\Columns\TextColumn::make('montant_n_plus_1')->label('N+1')->numeric(0),
                Tables\Columns\TextColumn::make('montant_n_plus_2')->label('N+2')->numeric(0),
                Tables\Columns\TextColumn::make('montant_n_plus_3')->label('N+3')->numeric(0),
            ])
            ->defaultSort('nature')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
