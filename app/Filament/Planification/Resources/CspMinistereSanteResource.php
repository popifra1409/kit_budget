<?php
// app/Filament/Planification/Resources/CspMinistereSanteResource.php

namespace App\Filament\Planification\Resources;

use App\Filament\Planification\Resources\CspMinistereSanteResource\Pages;
use App\Models\CspMinistereSante;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CspMinistereSanteResource extends Resource
{
    protected static ?string $model = CspMinistereSante::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Cadrage Stratégique';

    protected static ?string $navigationLabel = 'CSP Ministère de la Santé';

    protected static ?string $modelLabel = 'Cadre Stratégique Pluriannuel';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\DatePicker::make('periode_debut'),
            Forms\Components\DatePicker::make('periode_fin')->afterOrEqual('periode_debut'),
            Forms\Components\Select::make('statut')
                ->options([
                    'brouillon' => 'Brouillon',
                    'valide' => 'Validé',
                    'en_vigueur' => 'En vigueur',
                    'cloture' => 'Clôturé',
                ])
                ->default('brouillon')->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->searchable(),
                Tables\Columns\TextColumn::make('periode_debut')->date(),
                Tables\Columns\TextColumn::make('periode_fin')->date(),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'success' => ['valide', 'en_vigueur'],
                    'danger' => 'cloture',
                ]),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCspMinistereSantes::route('/'),
            'create' => Pages\CreateCspMinistereSante::route('/create'),
            'edit' => Pages\EditCspMinistereSante::route('/{record}/edit'),
        ];
    }
}
