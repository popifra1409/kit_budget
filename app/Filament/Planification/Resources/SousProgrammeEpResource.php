<?php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\SousProgrammeEpResource\Pages;
use App\Filament\Planification\Resources\SousProgrammeEpResource\RelationManagers\ActionsRelationManager;
use App\Models\SousProgrammeEp;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SousProgrammeEpResource extends Resource
{
    protected static ?string $model = SousProgrammeEp::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Plans Stratégiques EP';

    // Accessible uniquement depuis la fiche du Plan Stratégique (pas dans le menu principal)
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(50),
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
                Tables\Columns\TextColumn::make('planStrategiqueEp.libelle')->label('PSP'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => ['valide', 'en_vigueur'],
                    'danger' => 'cloture',
                ]),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            ActionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSousProgrammeEps::route('/'),
            'edit' => Pages\EditSousProgrammeEp::route('/{record}/edit'),
        ];
        // Pas de route 'create' : les sous-programmes se creent
        // depuis SousProgrammesRelationManager sur PlanStrategiqueEpResource.
    }
}
