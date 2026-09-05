<?php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\ActionSousProgrammeResource\Pages;
use App\Filament\Planification\Resources\ActionSousProgrammeResource\RelationManagers\ProjetsStrategiquesRelationManager;
use App\Models\ActionSousProgramme;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActionSousProgrammeResource extends Resource
{
    protected static ?string $model = ActionSousProgramme::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(10),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Select::make('responsable_id')
                ->label('Responsable')
                ->options(User::pluck('name', 'id'))
                ->searchable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°'),
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('libelle')->searchable(),
                Tables\Columns\TextColumn::make('sousProgrammeEp.libelle')->label('Sous-Programme'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            ProjetsStrategiquesRelationManager::class,
            \App\Filament\Planification\Resources\Concerns\IndicateursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActionSousProgrammes::route('/'),
            'edit' => Pages\EditActionSousProgramme::route('/{record}/edit'),
        ];
        // Pas de route 'create' : les actions se creent depuis
        // ActionsRelationManager sur SousProgrammeEpResource.
    }
}
