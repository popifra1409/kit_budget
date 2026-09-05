<?php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\IndicateurResource\Pages;
use App\Filament\Planification\Resources\IndicateurResource\RelationManagers\ValeursRelationManager;
use App\Models\Indicateur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IndicateurResource extends Resource
{
    protected static ?string $model = Indicateur::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->disabled(),
            Forms\Components\TextInput::make('libelle')->required()->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\TextColumn::make('libelle'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            ValeursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndicateurs::route('/'),
            'edit' => Pages\EditIndicateur::route('/{record}/edit'),
        ];
    }
}
