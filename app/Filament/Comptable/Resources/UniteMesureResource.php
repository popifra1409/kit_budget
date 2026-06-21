<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\UniteMesureResource\Pages;
use App\Models\UniteMesure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UniteMesureResource extends Resource
{
    protected static ?string $model           = UniteMesure::class;
    protected static ?string $navigationIcon  = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Unités de Mesure';
    protected static ?string $modelLabel      = 'Unité de Mesure';
    protected static ?string $pluralModelLabel = 'Unités de Mesure';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?int    $navigationSort  = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Unité de mesure')
                ->schema([
                    Forms\Components\TextInput::make('libelle')
                        ->label('Libellé')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ex: Boîte, Plaquette, Comprimé')
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('symbole')
                        ->label('Symbole')
                        ->maxLength(20)
                        ->placeholder('Ex: bte, plq, cpr'),

                    Forms\Components\Toggle::make('actif')
                        ->label('Actif')
                        ->default(true)
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('symbole')
                    ->label('Symbole')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles liés')
                    ->counts('articles')
                    ->badge()->color('info'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')->boolean()->sortable(),
            ])
            ->defaultSort('libelle')
            ->filters([
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')->trueLabel('Actifs')->falseLabel('Inactifs'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->articles()->count() === 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUniteMesures::route('/'),
            'create' => Pages\CreateUniteMesure::route('/create'),
            'edit'   => Pages\EditUniteMesure::route('/{record}/edit'),
        ];
    }
}
