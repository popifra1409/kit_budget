<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\ConditionnementResource\Pages;
use App\Models\Conditionnement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConditionnementResource extends Resource
{
    protected static ?string $model           = Conditionnement::class;
    protected static ?string $navigationIcon  = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Conditionnements';
    protected static ?string $modelLabel      = 'Conditionnement';
    protected static ?string $pluralModelLabel = 'Conditionnements';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?int    $navigationSort  = 11;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_conditionnement') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_conditionnement') ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_conditionnement') ?? false;
    }
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_conditionnement') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Conditionnement')
                ->description('Ex: Plaquette de 10 comprimés, Flacon 100ml, Gel 30g — utilisé pour les articles de catégorie Pharmacie.')
                ->schema([
                    Forms\Components\TextInput::make('libelle')
                        ->label('Libellé')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ex: Plaquette de 10 comprimés')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('actif')
                        ->label('Actif')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles liés')
                    ->counts('articles')
                    ->badge()->color('info'),

                Tables\Columns\TextColumn::make('lignes_expression_besoins_count')
                    ->label('Utilisé dans')
                    ->counts('lignesExpressionBesoins')
                    ->badge()->color('warning')
                    ->formatStateUsing(fn($state) => $state > 0 ? "{$state} expression(s)" : '—'),

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
                    ->visible(
                        fn($record) =>
                        $record->articles()->count() === 0
                            && $record->lignesExpressionBesoins()->count() === 0
                    ),
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
            'index'  => Pages\ListConditionnements::route('/'),
            'create' => Pages\CreateConditionnement::route('/create'),
            'edit'   => Pages\EditConditionnement::route('/{record}/edit'),
        ];
    }
}
